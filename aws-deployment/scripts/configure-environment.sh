#!/bin/bash

# Environment Configuration Script for AWS Deployment
# This script helps configure the environment variables needed for deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_header() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}================================${NC}"
}

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to prompt for input with validation
prompt_input() {
    local prompt="$1"
    local var_name="$2"
    local required="$3"
    local default_value="$4"
    
    while true; do
        if [ -n "$default_value" ]; then
            read -p "$prompt [$default_value]: " input
            input=${input:-$default_value}
        else
            read -p "$prompt: " input
        fi
        
        if [ "$required" = "true" ] && [ -z "$input" ]; then
            print_error "This field is required. Please enter a value."
            continue
        fi
        
        eval "$var_name='$input'"
        break
    done
}

# Function to prompt for secure input
prompt_secure() {
    local prompt="$1"
    local var_name="$2"
    
    while true; do
        read -s -p "$prompt: " input
        echo
        if [ -z "$input" ]; then
            print_error "This field is required. Please enter a value."
            continue
        fi
        
        read -s -p "Confirm $prompt: " confirm
        echo
        
        if [ "$input" != "$confirm" ]; then
            print_error "Values don't match. Please try again."
            continue
        fi
        
        eval "$var_name='$input'"
        break
    done
}

# Function to validate AWS region
validate_region() {
    local region="$1"
    if aws ec2 describe-regions --region-names "$region" &> /dev/null; then
        return 0
    else
        return 1
    fi
}

# Function to list available key pairs
list_key_pairs() {
    local region="$1"
    print_status "Available EC2 Key Pairs in $region:"
    aws ec2 describe-key-pairs --region "$region" --query 'KeyPairs[].KeyName' --output table 2>/dev/null || {
        print_warning "Could not list key pairs. Make sure you have the correct permissions."
    }
}

# Function to validate certificate ARN
validate_certificate() {
    local cert_arn="$1"
    local region="$2"
    if aws acm describe-certificate --certificate-arn "$cert_arn" --region "$region" &> /dev/null; then
        return 0
    else
        return 1
    fi
}

# Function to list available certificates
list_certificates() {
    local region="$1"
    print_status "Available SSL Certificates in $region:"
    aws acm list-certificates --region "$region" --query 'CertificateSummaryList[].[DomainName,CertificateArn]' --output table 2>/dev/null || {
        print_warning "Could not list certificates. Make sure you have the correct permissions."
    }
}

# Main configuration function
main() {
    print_header "AWS Deployment Configuration"
    
    echo "This script will help you configure the environment variables needed for AWS deployment."
    echo "Please have the following information ready:"
    echo "- AWS region where you want to deploy"
    echo "- EC2 Key Pair name for SSH access"
    echo "- Domain name for your application"
    echo "- SSL certificate ARN from AWS Certificate Manager"
    echo "- Database password"
    echo ""
    
    # AWS Region
    while true; do
        prompt_input "AWS Region" AWS_REGION true "us-east-1"
        if validate_region "$AWS_REGION"; then
            print_status "Region $AWS_REGION is valid"
            break
        else
            print_error "Invalid AWS region. Please enter a valid region."
        fi
    done
    
    # EC2 Key Pair
    echo ""
    list_key_pairs "$AWS_REGION"
    echo ""
    while true; do
        prompt_input "EC2 Key Pair Name" KEY_PAIR_NAME true
        # Validate key pair exists
        if aws ec2 describe-key-pairs --key-names "$KEY_PAIR_NAME" --region "$AWS_REGION" &> /dev/null; then
            print_status "Key pair $KEY_PAIR_NAME found"
            break
        else
            print_error "Key pair $KEY_PAIR_NAME not found in $AWS_REGION. Please check the name."
        fi
    done
    
    # Domain Name
    echo ""
    prompt_input "Domain Name (e.g., integration.lagunatools.com)" DOMAIN_NAME true
    
    # SSL Certificate
    echo ""
    list_certificates "$AWS_REGION"
    echo ""
    while true; do
        prompt_input "SSL Certificate ARN" CERTIFICATE_ARN true
        if validate_certificate "$CERTIFICATE_ARN" "$AWS_REGION"; then
            print_status "Certificate ARN is valid"
            break
        else
            print_error "Invalid certificate ARN or certificate not found in $AWS_REGION"
        fi
    done
    
    # Database Password
    echo ""
    prompt_secure "Database Master Password (8-41 characters)" DB_PASSWORD
    
    # Instance Type
    echo ""
    prompt_input "EC2 Instance Type" INSTANCE_TYPE false "t3.small"
    INSTANCE_TYPE=${INSTANCE_TYPE:-t3.small}
    
    # Database Instance Type
    prompt_input "RDS Instance Type" DB_INSTANCE_TYPE false "db.t3.micro"
    DB_INSTANCE_TYPE=${DB_INSTANCE_TYPE:-db.t3.micro}
    
    # Environment
    prompt_input "Environment Name" ENVIRONMENT false "production"
    ENVIRONMENT=${ENVIRONMENT:-production}
    
    # Create .env file for deployment
    ENV_FILE="aws-deployment/.env"
    print_status "Creating environment file: $ENV_FILE"
    
    cat > "$ENV_FILE" << EOF
# AWS Deployment Configuration
# Generated on $(date)

# AWS Configuration
AWS_REGION=$AWS_REGION

# EC2 Configuration
KEY_PAIR_NAME=$KEY_PAIR_NAME
INSTANCE_TYPE=$INSTANCE_TYPE

# Application Configuration
DOMAIN_NAME=$DOMAIN_NAME
CERTIFICATE_ARN=$CERTIFICATE_ARN
ENVIRONMENT=$ENVIRONMENT

# Database Configuration
DB_PASSWORD=$DB_PASSWORD
DB_INSTANCE_TYPE=$DB_INSTANCE_TYPE

# Project Configuration
PROJECT_NAME=laguna-3dcart-netsuite
EOF
    
    print_status "Environment configuration saved to $ENV_FILE"
    
    # Create deployment command
    print_header "Deployment Instructions"
    echo "Your environment has been configured. To deploy, run:"
    echo ""
    echo -e "${GREEN}cd aws-deployment${NC}"
    echo -e "${GREEN}source .env${NC}"
    echo -e "${GREEN}./scripts/deploy.sh${NC}"
    echo ""
    
    print_warning "Before deploying, make sure you have:"
    echo "1. AWS CLI installed and configured with appropriate permissions"
    echo "2. Created an SSL certificate in AWS Certificate Manager for your domain"
    echo "3. Created an EC2 Key Pair for SSH access"
    echo "4. Configured your domain's DNS to point to the Load Balancer (after deployment)"
    
    print_header "Next Steps"
    echo "1. Review the generated .env file"
    echo "2. Run the deployment script"
    echo "3. Configure your application credentials"
    echo "4. Set up database schema"
    echo "5. Test your integrations"
}

# Check if AWS CLI is available
if ! command -v aws &> /dev/null; then
    print_error "AWS CLI is not installed. Please install it first."
    exit 1
fi

# Check if AWS CLI is configured
if ! aws sts get-caller-identity &> /dev/null; then
    print_error "AWS CLI is not configured. Please run 'aws configure' first."
    exit 1
fi

# Create aws-deployment directory if it doesn't exist
mkdir -p aws-deployment

# Run main function
main "$@"