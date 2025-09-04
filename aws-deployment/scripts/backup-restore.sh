#!/bin/bash

# Backup and Restore Script for 3DCart NetSuite Integration
# This script handles database backups, application backups, and restoration

set -e

# Configuration
PROJECT_NAME="laguna-3dcart-netsuite"
ENVIRONMENT="production"
AWS_REGION="us-east-1"
BACKUP_RETENTION_DAYS=30

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to get RDS instance identifier
get_rds_instance() {
    aws rds describe-db-instances \
        --query "DBInstances[?contains(DBInstanceIdentifier, '${PROJECT_NAME}-${ENVIRONMENT}')].DBInstanceIdentifier" \
        --output text \
        --region "$AWS_REGION"
}

# Function to get S3 bucket name
get_s3_bucket() {
    aws cloudformation describe-stacks \
        --stack-name "${PROJECT_NAME}-${ENVIRONMENT}-app" \
        --query 'Stacks[0].Outputs[?OutputKey==`ApplicationBucketName`].OutputValue' \
        --output text \
        --region "$AWS_REGION"
}

# Function to create database backup
backup_database() {
    local db_instance=$(get_rds_instance)
    local backup_id="${PROJECT_NAME}-${ENVIRONMENT}-manual-$(date +%Y%m%d-%H%M%S)"
    
    if [ -z "$db_instance" ]; then
        print_error "Could not find RDS instance"
        return 1
    fi
    
    print_status "Creating database snapshot: $backup_id"
    
    aws rds create-db-snapshot \
        --db-instance-identifier "$db_instance" \
        --db-snapshot-identifier "$backup_id" \
        --region "$AWS_REGION"
    
    print_status "Waiting for snapshot to complete..."
    aws rds wait db-snapshot-completed \
        --db-snapshot-identifier "$backup_id" \
        --region "$AWS_REGION"
    
    print_status "Database backup completed: $backup_id"
    echo "$backup_id"
}

# Function to restore database from backup
restore_database() {
    local snapshot_id=$1
    local new_instance_id="${PROJECT_NAME}-${ENVIRONMENT}-restored-$(date +%Y%m%d-%H%M%S)"
    
    if [ -z "$snapshot_id" ]; then
        print_error "Snapshot ID is required"
        return 1
    fi
    
    print_status "Restoring database from snapshot: $snapshot_id"
    
    # Get original instance details
    local original_instance=$(get_rds_instance)
    local instance_class=$(aws rds describe-db-instances \
        --db-instance-identifier "$original_instance" \
        --query 'DBInstances[0].DBInstanceClass' \
        --output text \
        --region "$AWS_REGION")
    
    local subnet_group=$(aws rds describe-db-instances \
        --db-instance-identifier "$original_instance" \
        --query 'DBInstances[0].DBSubnetGroup.DBSubnetGroupName' \
        --output text \
        --region "$AWS_REGION")
    
    local security_groups=$(aws rds describe-db-instances \
        --db-instance-identifier "$original_instance" \
        --query 'DBInstances[0].VpcSecurityGroups[].VpcSecurityGroupId' \
        --output text \
        --region "$AWS_REGION")
    
    # Restore from snapshot
    aws rds restore-db-instance-from-db-snapshot \
        --db-instance-identifier "$new_instance_id" \
        --db-snapshot-identifier "$snapshot_id" \
        --db-instance-class "$instance_class" \
        --db-subnet-group-name "$subnet_group" \
        --vpc-security-group-ids $security_groups \
        --region "$AWS_REGION"
    
    print_status "Waiting for database restoration to complete..."
    aws rds wait db-instance-available \
        --db-instance-identifier "$new_instance_id" \
        --region "$AWS_REGION"
    
    print_status "Database restored to new instance: $new_instance_id"
    print_warning "Update your application configuration to use the new database endpoint"
    
    # Get new endpoint
    local new_endpoint=$(aws rds describe-db-instances \
        --db-instance-identifier "$new_instance_id" \
        --query 'DBInstances[0].Endpoint.Address' \
        --output text \
        --region "$AWS_REGION")
    
    print_status "New database endpoint: $new_endpoint"
}

# Function to backup application files
backup_application() {
    local bucket_name=$(get_s3_bucket)
    local backup_key="backups/application/$(date +%Y%m%d-%H%M%S)"
    
    if [ -z "$bucket_name" ]; then
        print_error "Could not find S3 bucket"
        return 1
    fi
    
    print_status "Backing up application files to S3..."
    
    # Create backup of current application
    aws s3 sync "s3://$bucket_name/app/" "s3://$bucket_name/$backup_key/" \
        --region "$AWS_REGION"
    
    print_status "Application backup completed: s3://$bucket_name/$backup_key/"
    echo "$backup_key"
}

# Function to restore application files
restore_application() {
    local backup_key=$1
    local bucket_name=$(get_s3_bucket)
    
    if [ -z "$backup_key" ]; then
        print_error "Backup key is required"
        return 1
    fi
    
    if [ -z "$bucket_name" ]; then
        print_error "Could not find S3 bucket"
        return 1
    fi
    
    print_status "Restoring application files from: s3://$bucket_name/$backup_key/"
    
    # Backup current version first
    local current_backup="backups/application/pre-restore-$(date +%Y%m%d-%H%M%S)"
    aws s3 sync "s3://$bucket_name/app/" "s3://$bucket_name/$current_backup/" \
        --region "$AWS_REGION"
    
    # Restore from backup
    aws s3 sync "s3://$bucket_name/$backup_key/" "s3://$bucket_name/app/" \
        --delete \
        --region "$AWS_REGION"
    
    print_status "Application files restored successfully"
    print_status "Previous version backed up to: s3://$bucket_name/$current_backup/"
}

# Function to cleanup old backups
cleanup_old_backups() {
    local cutoff_date=$(date -d "$BACKUP_RETENTION_DAYS days ago" +%Y-%m-%d)
    
    print_status "Cleaning up backups older than $BACKUP_RETENTION_DAYS days ($cutoff_date)"
    
    # Cleanup RDS snapshots
    local old_snapshots=$(aws rds describe-db-snapshots \
        --snapshot-type manual \
        --query "DBSnapshots[?contains(DBSnapshotIdentifier, '${PROJECT_NAME}-${ENVIRONMENT}-manual') && SnapshotCreateTime < '$cutoff_date'].DBSnapshotIdentifier" \
        --output text \
        --region "$AWS_REGION")
    
    for snapshot in $old_snapshots; do
        if [ -n "$snapshot" ]; then
            print_status "Deleting old snapshot: $snapshot"
            aws rds delete-db-snapshot \
                --db-snapshot-identifier "$snapshot" \
                --region "$AWS_REGION"
        fi
    done
    
    # Cleanup S3 backups
    local bucket_name=$(get_s3_bucket)
    if [ -n "$bucket_name" ]; then
        print_status "Cleaning up old S3 backups..."
        aws s3api list-objects-v2 \
            --bucket "$bucket_name" \
            --prefix "backups/" \
            --query "Contents[?LastModified < '$cutoff_date'].Key" \
            --output text \
            --region "$AWS_REGION" | \
        while read -r key; do
            if [ -n "$key" ]; then
                print_status "Deleting old backup: s3://$bucket_name/$key"
                aws s3 rm "s3://$bucket_name/$key" --region "$AWS_REGION"
            fi
        done
    fi
    
    print_status "Cleanup completed"
}

# Function to list available backups
list_backups() {
    print_status "Available Database Snapshots:"
    aws rds describe-db-snapshots \
        --snapshot-type manual \
        --query "DBSnapshots[?contains(DBSnapshotIdentifier, '${PROJECT_NAME}-${ENVIRONMENT}')].{ID:DBSnapshotIdentifier,Created:SnapshotCreateTime,Status:Status}" \
        --output table \
        --region "$AWS_REGION"
    
    print_status "Available Application Backups:"
    local bucket_name=$(get_s3_bucket)
    if [ -n "$bucket_name" ]; then
        aws s3api list-objects-v2 \
            --bucket "$bucket_name" \
            --prefix "backups/application/" \
            --query "Contents[].{Key:Key,LastModified:LastModified,Size:Size}" \
            --output table \
            --region "$AWS_REGION"
    fi
}

# Function to create full backup (database + application)
full_backup() {
    print_status "Starting full backup..."
    
    local db_backup=$(backup_database)
    local app_backup=$(backup_application)
    
    print_status "Full backup completed:"
    print_status "  Database: $db_backup"
    print_status "  Application: $app_backup"
    
    # Create backup manifest
    local bucket_name=$(get_s3_bucket)
    local manifest_key="backups/manifests/$(date +%Y%m%d-%H%M%S).json"
    
    cat > /tmp/backup-manifest.json << EOF
{
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "database_snapshot": "$db_backup",
    "application_backup": "$app_backup",
    "project": "$PROJECT_NAME",
    "environment": "$ENVIRONMENT"
}
EOF
    
    aws s3 cp /tmp/backup-manifest.json "s3://$bucket_name/$manifest_key" \
        --region "$AWS_REGION"
    
    rm /tmp/backup-manifest.json
    
    print_status "Backup manifest saved: s3://$bucket_name/$manifest_key"
}

# Main function
main() {
    case "$1" in
        "backup-db")
            backup_database
            ;;
        "restore-db")
            restore_database "$2"
            ;;
        "backup-app")
            backup_application
            ;;
        "restore-app")
            restore_application "$2"
            ;;
        "full-backup")
            full_backup
            ;;
        "list")
            list_backups
            ;;
        "cleanup")
            cleanup_old_backups
            ;;
        *)
            echo "Usage: $0 {backup-db|restore-db <snapshot-id>|backup-app|restore-app <backup-key>|full-backup|list|cleanup}"
            echo ""
            echo "Commands:"
            echo "  backup-db                    Create database snapshot"
            echo "  restore-db <snapshot-id>     Restore database from snapshot"
            echo "  backup-app                   Backup application files to S3"
            echo "  restore-app <backup-key>     Restore application files from S3"
            echo "  full-backup                  Create full backup (database + application)"
            echo "  list                         List available backups"
            echo "  cleanup                      Remove old backups"
            exit 1
            ;;
    esac
}

# Check if AWS CLI is available
if ! command -v aws &> /dev/null; then
    print_error "AWS CLI is not installed"
    exit 1
fi

# Check if AWS CLI is configured
if ! aws sts get-caller-identity &> /dev/null; then
    print_error "AWS CLI is not configured"
    exit 1
fi

# Run main function
main "$@"