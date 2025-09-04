#!/bin/bash

# Laguna Integrations - AWS Deployment Script
# This script deploys the application to AWS using CloudFormation

set -e

# Configuration
PROJECT_NAME="laguna-integrations"
ENVIRONMENT="production"
AWS_REGION="us-east-2"
STACK_PREFIX="${PROJECT_NAME}-${ENVIRONMENT}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if AWS CLI is installed and configured
check_aws_cli() {
    if ! command -v aws &> /dev/null; then
        print_error "AWS CLI is not installed. Please install it first."
        exit 1
    fi
    
    if ! aws sts get-caller-identity &> /dev/null; then
        print_error "AWS CLI is not configured. Please run 'aws configure' first."
        exit 1
    fi
    
    print_status "AWS CLI is configured and ready"
}

# Function to validate required parameters
validate_parameters() {
    if [ -z "$KEY_PAIR_NAME" ]; then
        print_error "KEY_PAIR_NAME environment variable is required"
        exit 1
    fi
    
    if [ -z "$DOMAIN_NAME" ]; then
        print_error "DOMAIN_NAME environment variable is required"
        exit 1
    fi
    
    if [ -z "$CERTIFICATE_ARN" ]; then
        print_error "CERTIFICATE_ARN environment variable is required"
        exit 1
    fi
    
    if [ -z "$DB_PASSWORD" ]; then
        print_error "DB_PASSWORD environment variable is required"
        exit 1
    fi
}

# Function to deploy CloudFormation stack
deploy_stack() {
    local stack_name=$1
    local template_file=$2
    local parameters=$3
    
    print_status "Deploying stack: $stack_name"
    
    if aws cloudformation describe-stacks --stack-name "$stack_name" --region "$AWS_REGION" &> /dev/null; then
        print_status "Stack exists, updating..."
        aws cloudformation update-stack \
            --stack-name "$stack_name" \
            --template-body "file://$template_file" \
            --parameters "$parameters" \
            --capabilities CAPABILITY_NAMED_IAM \
            --region "$AWS_REGION"
    else
        print_status "Creating new stack..."
        aws cloudformation create-stack \
            --stack-name "$stack_name" \
            --template-body "file://$template_file" \
            --parameters "$parameters" \
            --capabilities CAPABILITY_NAMED_IAM \
            --region "$AWS_REGION"
    fi
    
    print_status "Waiting for stack operation to complete..."
    aws cloudformation wait stack-update-complete --stack-name "$stack_name" --region "$AWS_REGION" 2>/dev/null || \
    aws cloudformation wait stack-create-complete --stack-name "$stack_name" --region "$AWS_REGION"
    
    print_status "Stack $stack_name deployed successfully"
}

# Function to upload application files to S3
upload_application() {
    local bucket_name=$1
    
    print_status "Preparing application files for upload..."
    
    # Create temporary directory for deployment
    TEMP_DIR=$(mktemp -d)
    
    # Copy application files (excluding development files)
    rsync -av --exclude='aws-deployment' \
              --exclude='.git' \
              --exclude='node_modules' \
              --exclude='vendor' \
              --exclude='*.log' \
              --exclude='uploads/*' \
              --exclude='logs/*' \
              --exclude='.env' \
              --exclude='test-*.php' \
              --exclude='debug-*.php' \
              ./ "$TEMP_DIR/"
    
    # Create necessary directories
    mkdir -p "$TEMP_DIR/logs"
    mkdir -p "$TEMP_DIR/uploads"
    
    # Set proper permissions in the temp directory
    chmod -R 755 "$TEMP_DIR"
    chmod -R 777 "$TEMP_DIR/logs"
    chmod -R 777 "$TEMP_DIR/uploads"
    
    print_status "Uploading application files to S3..."
    aws s3 sync "$TEMP_DIR/" "s3://$bucket_name/app/" --delete --region "$AWS_REGION"
    
    # Clean up
    rm -rf "$TEMP_DIR"
    
    print_status "Application files uploaded successfully"
}

# Function to create SSM parameters for configuration
create_ssm_parameters() {
    print_status "Creating SSM parameters for application configuration..."
    
    # Database configuration
    aws ssm put-parameter \
        --name "/${PROJECT_NAME}/${ENVIRONMENT}/database/host" \
        --value "$(aws cloudformation describe-stacks --stack-name "${STACK_PREFIX}-database" --query 'Stacks[0].Outputs[?OutputKey==`DatabaseEndpoint`].OutputValue' --output text --region "$AWS_REGION")" \
        --type "String" \
        --overwrite \
        --region "$AWS_REGION"
    
    aws ssm put-parameter \
        --name "/${PROJECT_NAME}/${ENVIRONMENT}/database/port" \
        --value "$(aws cloudformation describe-stacks --stack-name "${STACK_PREFIX}-database" --query 'Stacks[0].Outputs[?OutputKey==`DatabasePort`].OutputValue' --output text --region "$AWS_REGION")" \
        --type "String" \
        --overwrite \
        --region "$AWS_REGION"
    
    aws ssm put-parameter \
        --name "/${PROJECT_NAME}/${ENVIRONMENT}/database/name" \
        --value "laguna_integration" \
        --type "String" \
        --overwrite \
        --region "$AWS_REGION"
    
    aws ssm put-parameter \
        --name "/${PROJECT_NAME}/${ENVIRONMENT}/database/username" \
        --value "admin" \
        --type "String" \
        --overwrite \
        --region "$AWS_REGION"
    
    aws ssm put-parameter \
        --name "/${PROJECT_NAME}/${ENVIRONMENT}/database/password" \
        --value "$DB_PASSWORD" \
        --type "SecureString" \
        --overwrite \
        --region "$AWS_REGION"
    
    # Application configuration
    aws ssm put-parameter \
        --name "/${PROJECT_NAME}/${ENVIRONMENT}/app/environment" \
        --value "$ENVIRONMENT" \
        --type "String" \
        --overwrite \
        --region "$AWS_REGION"
    
    aws ssm put-parameter \
        --name "/${PROJECT_NAME}/${ENVIRONMENT}/app/domain" \
        --value "$DOMAIN_NAME" \
        --type "String" \
        --overwrite \
        --region "$AWS_REGION"
    
    print_status "SSM parameters created successfully"
}

# Main deployment function
main() {
    print_status "Starting AWS deployment for $PROJECT_NAME"
    
    # Check prerequisites
    check_aws_cli
    validate_parameters
    
    # Deploy infrastructure stacks
    print_status "Deploying VPC infrastructure..."
    deploy_stack "${STACK_PREFIX}-vpc" \
                 "cloudformation/vpc-infrastructure.yaml" \
                 "ParameterKey=ProjectName,ParameterValue=$PROJECT_NAME ParameterKey=Environment,ParameterValue=$ENVIRONMENT"
    
    print_status "Deploying RDS database..."
    deploy_stack "${STACK_PREFIX}-database" \
                 "cloudformation/rds-database.yaml" \
                 "ParameterKey=ProjectName,ParameterValue=$PROJECT_NAME ParameterKey=Environment,ParameterValue=$ENVIRONMENT ParameterKey=DBMasterPassword,ParameterValue=$DB_PASSWORD"
    
    print_status "Deploying application infrastructure..."
    deploy_stack "${STACK_PREFIX}-app" \
                 "cloudformation/application-infrastructure.yaml" \
                 "ParameterKey=ProjectName,ParameterValue=$PROJECT_NAME ParameterKey=Environment,ParameterValue=$ENVIRONMENT ParameterKey=KeyPairName,ParameterValue=$KEY_PAIR_NAME ParameterKey=DomainName,ParameterValue=$DOMAIN_NAME ParameterKey=CertificateArn,ParameterValue=$CERTIFICATE_ARN"
    
    # Get S3 bucket name
    BUCKET_NAME=$(aws cloudformation describe-stacks --stack-name "${STACK_PREFIX}-app" --query 'Stacks[0].Outputs[?OutputKey==`ApplicationBucketName`].OutputValue' --output text --region "$AWS_REGION")
    
    # Upload application files
    upload_application "$BUCKET_NAME"
    
    # Create SSM parameters
    create_ssm_parameters
    
    # Get ALB DNS name
    ALB_DNS=$(aws cloudformation describe-stacks --stack-name "${STACK_PREFIX}-app" --query 'Stacks[0].Outputs[?OutputKey==`LoadBalancerDNS`].OutputValue' --output text --region "$AWS_REGION")
    
    print_status "Deployment completed successfully!"
    print_status "Application Load Balancer DNS: $ALB_DNS"
    print_status "Please update your DNS records to point $DOMAIN_NAME to $ALB_DNS"
    print_warning "Don't forget to:"
    print_warning "1. Configure your API credentials in the application"
    print_warning "2. Run database migrations"
    print_warning "3. Test all integrations"
}

# Run main function
main "$@"