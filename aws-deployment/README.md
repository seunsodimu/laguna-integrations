# AWS Deployment Guide - 3DCart NetSuite Integration

This guide provides comprehensive instructions for deploying the 3DCart NetSuite Integration system to AWS infrastructure.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Architecture Overview](#architecture-overview)
3. [Cost Estimation](#cost-estimation)
4. [Pre-Deployment Setup](#pre-deployment-setup)
5. [Deployment Process](#deployment-process)
6. [Post-Deployment Configuration](#post-deployment-configuration)
7. [Monitoring and Maintenance](#monitoring-and-maintenance)
8. [Troubleshooting](#troubleshooting)
9. [Security Considerations](#security-considerations)

## Prerequisites

### Required Tools
- AWS CLI v2.x installed and configured
- Git (for version control)
- SSH client for server access
- Domain name with DNS management access

### AWS Account Requirements
- AWS account with appropriate permissions
- AWS CLI configured with access keys
- EC2 Key Pair created in your target region
- SSL certificate in AWS Certificate Manager

### Required Permissions
Your AWS user/role needs the following permissions:
- EC2 (full access)
- RDS (full access)
- S3 (full access)
- CloudFormation (full access)
- IAM (create roles and policies)
- CloudWatch (full access)
- Systems Manager (Parameter Store access)
- Certificate Manager (read access)

## Architecture Overview

### AWS Services Used

| Service | Purpose | Instance Type | Estimated Cost/Month |
|---------|---------|---------------|---------------------|
| EC2 | Web servers | t3.small | $15-20 |
| RDS MySQL | Database | db.t3.micro | $15-20 |
| Application Load Balancer | Load balancing & SSL | - | $16 |
| S3 | File storage & backups | - | $1-5 |
| CloudFront | CDN (optional) | - | $1-10 |
| CloudWatch | Monitoring & logs | - | $5-15 |
| **Total** | | | **$53-86** |

### Architecture Diagram

```
Internet
    ↓
Route 53 (DNS)
    ↓
CloudFront (CDN) [Optional]
    ↓
Application Load Balancer (SSL Termination)
    ↓
Auto Scaling Group (EC2 Instances)
    ↓
RDS MySQL (Multi-AZ for Production)
    ↓
S3 (File Storage & Backups)
```

## Cost Estimation

### Monthly Costs (US East-1)

**Minimum Configuration:**
- EC2 t3.micro: $8.50
- RDS db.t3.micro: $15.30
- ALB: $16.20
- S3: $1-3
- CloudWatch: $3-5
- **Total: ~$44-48/month**

**Recommended Configuration:**
- EC2 t3.small: $16.79
- RDS db.t3.micro: $15.30
- ALB: $16.20
- S3: $3-5
- CloudWatch: $5-10
- **Total: ~$56-67/month**

**Production Configuration:**
- EC2 t3.medium (2 instances): $67.16
- RDS db.t3.small (Multi-AZ): $61.20
- ALB: $16.20
- S3: $5-10
- CloudWatch: $10-20
- **Total: ~$159-174/month**

## Pre-Deployment Setup

### 1. Create EC2 Key Pair

```bash
# Create a new key pair
aws ec2 create-key-pair \
    --key-name laguna-integration-key \
    --query 'KeyMaterial' \
    --output text > ~/.ssh/laguna-integration-key.pem

# Set proper permissions
chmod 400 ~/.ssh/laguna-integration-key.pem
```

### 2. Request SSL Certificate

```bash
# Request certificate (replace with your domain)
aws acm request-certificate \
    --domain-name integration.lagunatools.com \
    --validation-method DNS \
    --region us-east-1

# Note the CertificateArn from the output
```

### 3. Configure Environment

```bash
# Clone the repository
git clone <your-repo-url>
cd laguna_3dcart_netsuite

# Make scripts executable
chmod +x aws-deployment/scripts/*.sh

# Run configuration script
./aws-deployment/scripts/configure-environment.sh
```

## Deployment Process

### Step 1: Configure Environment Variables

```bash
# Source the environment configuration
cd aws-deployment
source .env

# Verify configuration
echo "Region: $AWS_REGION"
echo "Domain: $DOMAIN_NAME"
echo "Key Pair: $KEY_PAIR_NAME"
```

### Step 2: Deploy Infrastructure

```bash
# Deploy all infrastructure
./scripts/deploy.sh
```

The deployment script will:
1. Create VPC and networking components
2. Deploy RDS database
3. Create EC2 instances with Auto Scaling
4. Set up Application Load Balancer
5. Upload application files to S3
6. Configure Systems Manager parameters

### Step 3: Verify Deployment

```bash
# Check stack status
aws cloudformation describe-stacks \
    --stack-name laguna-3dcart-netsuite-production-app \
    --query 'Stacks[0].StackStatus'

# Get Load Balancer DNS name
aws cloudformation describe-stacks \
    --stack-name laguna-3dcart-netsuite-production-app \
    --query 'Stacks[0].Outputs[?OutputKey==`LoadBalancerDNS`].OutputValue' \
    --output text
```

### Step 4: Update DNS Records

Update your domain's DNS to point to the Load Balancer:

```
Type: CNAME
Name: integration.lagunatools.com
Value: <LoadBalancer-DNS-Name>
TTL: 300
```

## Post-Deployment Configuration

### 1. Initialize Database

```bash
# SSH into EC2 instance
ssh -i ~/.ssh/laguna-integration-key.pem ec2-user@<instance-ip>

# Run database setup
cd /var/www/html
php aws-deployment/scripts/setup-database.php
```

### 2. Configure Application Credentials

```bash
# Edit credentials file
sudo nano config/credentials.php

# Update with your API credentials:
# - 3DCart API credentials
# - NetSuite OAuth credentials
# - SendGrid/Brevo API keys
```

### 3. Test Application

```bash
# Test application health
curl https://integration.lagunatools.com/status.php

# Test webhook endpoint
curl -X POST https://integration.lagunatools.com/webhook.php \
    -H "Content-Type: application/json" \
    -d '{"test": "data"}'
```

### 4. Configure 3DCart Webhook

In your 3DCart admin panel:
1. Go to Settings → General → Webhooks
2. Add webhook URL: `https://integration.lagunatools.com/webhook.php`
3. Select events: Order Created, Order Updated
4. Set secret key (same as in your configuration)

## Monitoring and Maintenance

### 1. Deploy Monitoring Stack

```bash
# Deploy CloudWatch monitoring
aws cloudformation create-stack \
    --stack-name laguna-3dcart-netsuite-production-monitoring \
    --template-body file://cloudformation/monitoring.yaml \
    --parameters ParameterKey=ProjectName,ParameterValue=laguna-3dcart-netsuite \
                 ParameterKey=Environment,ParameterValue=production \
                 ParameterKey=NotificationEmail,ParameterValue=admin@lagunatools.com
```

### 2. Set Up Automated Backups

```bash
# Create daily backup cron job
echo "0 2 * * * /var/www/html/aws-deployment/scripts/backup-restore.sh full-backup" | sudo crontab -

# Create weekly cleanup job
echo "0 3 * * 0 /var/www/html/aws-deployment/scripts/backup-restore.sh cleanup" | sudo crontab -
```

### 3. Monitor Application

- **CloudWatch Dashboard**: Monitor metrics and logs
- **CloudWatch Alarms**: Get notified of issues
- **Application Logs**: Check `/var/www/html/logs/app.log`
- **Health Check**: Monitor `/status.php` endpoint

### 4. Regular Maintenance Tasks

```bash
# Weekly: Update system packages
sudo yum update -y

# Monthly: Optimize database
mysql -h <rds-endpoint> -u admin -p -e "CALL CleanupExpiredSessions(); CALL CleanupOldActivityLogs();"

# Monthly: Review and rotate logs
sudo logrotate -f /etc/logrotate.conf
```

## Troubleshooting

### Common Issues

#### 1. Application Not Loading
```bash
# Check EC2 instance status
aws ec2 describe-instances --filters "Name=tag:Name,Values=laguna-3dcart-netsuite-production-web-server"

# Check application logs
sudo tail -f /var/www/html/logs/app.log

# Check Apache logs
sudo tail -f /var/log/httpd/error_log
```

#### 2. Database Connection Issues
```bash
# Test database connectivity
mysql -h <rds-endpoint> -u admin -p -e "SELECT 1;"

# Check RDS status
aws rds describe-db-instances --db-instance-identifier laguna-3dcart-netsuite-production-db
```

#### 3. SSL Certificate Issues
```bash
# Check certificate status
aws acm describe-certificate --certificate-arn <certificate-arn>

# Test SSL connection
openssl s_client -connect integration.lagunatools.com:443
```

#### 4. High CPU/Memory Usage
```bash
# Check system resources
top
free -h
df -h

# Scale up if needed
aws autoscaling update-auto-scaling-group \
    --auto-scaling-group-name laguna-3dcart-netsuite-production-asg \
    --desired-capacity 2
```

### Log Locations

- **Application Logs**: `/var/www/html/logs/app.log`
- **Apache Logs**: `/var/log/httpd/access_log`, `/var/log/httpd/error_log`
- **PHP-FPM Logs**: `/var/log/php-fpm/www-error.log`
- **System Logs**: `/var/log/messages`
- **CloudWatch Logs**: AWS Console → CloudWatch → Log Groups

## Security Considerations

### 1. Network Security
- All traffic encrypted with SSL/TLS
- Database in private subnets
- Security groups restrict access
- No direct SSH access from internet (use bastion host for production)

### 2. Application Security
- Strong passwords for database and admin accounts
- API credentials stored in AWS Systems Manager Parameter Store
- Regular security updates
- Input validation and sanitization

### 3. Data Protection
- Database encryption at rest
- S3 bucket encryption
- Regular automated backups
- Access logging enabled

### 4. Compliance
- PCI DSS considerations for payment data
- GDPR compliance for customer data
- Regular security audits
- Access control and monitoring

## Scaling Considerations

### Horizontal Scaling
- Auto Scaling Group handles traffic spikes
- Application Load Balancer distributes traffic
- Stateless application design

### Vertical Scaling
- Easy instance type upgrades
- RDS instance scaling
- Storage auto-scaling enabled

### Performance Optimization
- CloudFront CDN for static assets
- Database query optimization
- Application caching
- Connection pooling

## Disaster Recovery

### Backup Strategy
- Automated daily database snapshots
- Application file backups to S3
- Cross-region backup replication (optional)
- Point-in-time recovery capability

### Recovery Procedures
- Database restore from snapshot
- Application restore from S3 backup
- Infrastructure recreation from CloudFormation
- DNS failover (if multi-region)

## Support and Maintenance

### Regular Tasks
- [ ] Monitor CloudWatch alarms
- [ ] Review application logs weekly
- [ ] Update system packages monthly
- [ ] Test backup/restore procedures quarterly
- [ ] Review and update security settings quarterly
- [ ] Performance optimization review semi-annually

### Emergency Contacts
- AWS Support (if applicable)
- Application development team
- Infrastructure team
- Business stakeholders

---

## Quick Reference Commands

```bash
# Deploy infrastructure
./scripts/deploy.sh

# Create backup
./scripts/backup-restore.sh full-backup

# List backups
./scripts/backup-restore.sh list

# Check application status
curl https://integration.lagunatools.com/status.php

# View recent logs
sudo tail -f /var/www/html/logs/app.log

# Restart services
sudo systemctl restart httpd php-fpm

# Scale application
aws autoscaling set-desired-capacity \
    --auto-scaling-group-name laguna-3dcart-netsuite-production-asg \
    --desired-capacity 2
```

For additional support, refer to the application documentation or contact the development team.