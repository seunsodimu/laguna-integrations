# Customer isPerson Analysis - Non-Dropship Orders

## 🔍 Question Answered

**For orders that aren't dropship, what is the isPerson value of the customer searched for?**

## ✅ Answer

**isPerson = 'F' (False - Company Customers)**

## 📊 Analysis Results

### Order Distribution (Recent 50 Orders)
- **Total Orders**: 50
- **Dropship Orders**: 3 (6%)
- **Regular Orders**: 47 (94%)

### Payment Methods Found
- **Empty/Blank**: 34 orders (REGULAR)
- **Store Shipment**: 13 orders (REGULAR)  
- **Dropship to Customer**: 3 orders (DROPSHIP)

## 🔍 Customer Search Logic

### For Non-Dropship Orders, the system performs TWO searches:

#### 1. Store Customer Search
```sql
SELECT id, firstName, lastName, email, companyName, phone, isperson 
FROM customer 
WHERE email = '{QuestionList->email}' AND isPerson = 'F'
```
**Location**: `NetSuiteService.php` line 739

#### 2. Parent Company Search  
```sql
SELECT id, firstName, lastName, email, companyName, phone, isperson 
FROM customer 
WHERE (email = '{BillingEmail}' OR phone = '{BillingPhone}') AND isPerson = 'F'
```
**Location**: `NetSuiteService.php` lines 701-702

## 🏗️ Customer Creation Logic

### When customers are not found and need to be created:

| Order Type | isPerson Value | Customer Type | Code Location |
|------------|----------------|---------------|---------------|
| **Regular Orders** | `false` | Company Customer | Line 828 |
| **Dropship Orders** | `true` | Person Customer | Line 782 |

## 📋 Example Regular Orders

### Order #953 - Store Shipment
- **Billing Email**: info@woodcraftspokane.com
- **Billing Company**: Woodcraft Spokane 573
- **Store Customer Search**: `isPerson = 'F'`
- **Parent Company Search**: `isPerson = 'F'`
- **If Created**: `isPerson = false`

### Order #958 - Store Shipment  
- **Billing Email**: 320@woodcraft-sacto.com
- **Billing Company**: CyberSurfer, Inc dba Woodcraft
- **Store Customer Search**: `isPerson = 'F'`
- **Parent Company Search**: `isPerson = 'F'`
- **If Created**: `isPerson = false`

## 🔄 Complete Workflow for Non-Dropship Orders

1. **Extract Customer Email** from QuestionList
2. **IF valid email found**:
   - Search for Store Customer with `isPerson = 'F'`
   - IF found → Use existing customer
3. **Search for Parent Company** with `isPerson = 'F'` using:
   - BillingEmail OR BillingPhone
   - IF found → Use as parent for new customer
4. **Create New Customer** with:
   - `isPerson = false` (company customer)
   - Parent company if found

## ✅ Key Findings

- ✅ **All customer searches use isPerson = 'F'** - Only searches for company customers
- ✅ **Regular orders create company customers** - isPerson = false
- ✅ **Dropship orders create person customers** - isPerson = true  
- ✅ **No person customer searches** - System never searches for isPerson = 'T'
- ✅ **Consistent logic** - All non-dropship orders treated as company orders

## 🎯 Summary

**For orders that aren't dropship:**

1. **Customer Search**: Always searches for `isPerson = 'F'` (company customers)
2. **Customer Creation**: Always creates `isPerson = false` (company customers)
3. **Logic**: Non-dropship orders are treated as B2B company orders
4. **Consistency**: No person customers are ever searched for regular orders

This design makes sense because:
- **Dropship orders** are typically B2C (person customers)
- **Regular orders** are typically B2B (company customers)
- **Store shipments** are business-to-business transactions
- **Company customers** are the primary target for non-dropship orders