# 📤 **NETSUITE SALES ORDER PAYLOAD SAMPLES**

## 🎯 **API ENDPOINT**
```
POST {base_url}/services/rest/record/{version}/salesOrder
Content-Type: application/json
Authorization: OAuth 1.0 (with signature)
```

---

## 📋 **SAMPLE PAYLOADS**

### **1. Simple Order (Product Only)**

#### **3DCart Input Data:**
```json
{
  "OrderID": "1108410",
  "CustomerID": "341",
  "OrderDate": "2025-08-03 10:30:00",
  "OrderTotal": 329.00,
  "BillingFirstName": "Logan",
  "BillingLastName": "Williams",
  "BillingEmail": "lwilliams@oaktreesupplies.com",
  "OrderItemList": [
    {
      "CatalogID": "XP2001",
      "ItemName": "XP|20 20 Flexible Roller Conveyor Table",
      "ItemQuantity": 1,
      "ItemUnitPrice": 329.00
    }
  ]
}
```

#### **NetSuite Payload Sent:**
```json
{
  "entity": {
    "id": 12345
  },
  "subsidiary": {
    "id": 1
  },
  "department": {
    "id": 3
  },
  "istaxable": false,
  "tranDate": "2025-08-03",
  "memo": "Order imported from 3DCart - Order #1108410",
  "externalId": "3DCART_1108410",
  "custbodycustbody4": "3DCart Integration",
  "item": {
    "items": [
      {
        "item": {
          "id": 567
        },
        "quantity": 1.0,
        "rate": 329.00,
        "istaxable": false
      }
    ]
  }
}
```

---

### **2. Order with Tax**

#### **3DCart Input Data:**
```json
{
  "OrderID": "1108411",
  "CustomerID": "341",
  "OrderDate": "2025-08-03 11:00:00",
  "OrderTotal": 356.85,
  "SalesTax": 27.85,
  "BillingFirstName": "Logan",
  "BillingLastName": "Williams",
  "BillingEmail": "lwilliams@oaktreesupplies.com",
  "OrderItemList": [
    {
      "CatalogID": "XP2001",
      "ItemName": "XP|20 20 Flexible Roller Conveyor Table",
      "ItemQuantity": 1,
      "ItemUnitPrice": 329.00
    }
  ]
}
```

#### **NetSuite Payload Sent:**
```json
{
  "entity": {
    "id": 12345
  },
  "subsidiary": {
    "id": 1
  },
  "department": {
    "id": 3
  },
  "istaxable": false,
  "tranDate": "2025-08-03",
  "memo": "Order imported from 3DCart - Order #1108411",
  "externalId": "3DCART_1108411",
  "custbodycustbody4": "3DCart Integration",
  "item": {
    "items": [
      {
        "item": {
          "id": 567
        },
        "quantity": 1.0,
        "rate": 329.00,
        "istaxable": false
      },
      {
        "item": {
          "id": 2
        },
        "quantity": 1,
        "rate": 27.85,
        "istaxable": false
      }
    ]
  }
}
```

---

### **3. Order with Shipping**

#### **3DCart Input Data:**
```json
{
  "OrderID": "1108412",
  "CustomerID": "341",
  "OrderDate": "2025-08-03 12:00:00",
  "OrderTotal": 354.00,
  "ShippingCost": 25.00,
  "BillingFirstName": "Logan",
  "BillingLastName": "Williams",
  "BillingEmail": "lwilliams@oaktreesupplies.com",
  "ShipmentList": [
    {
      "ShipmentFirstName": "Logan",
      "ShipmentLastName": "Williams",
      "ShipmentCompany": "Oak Tree Supplies"
    }
  ],
  "ShippingAddress": "14110 Plank Street",
  "ShippingCity": "Fort Wayne",
  "ShippingState": "IN",
  "ShippingZipCode": "46818",
  "ShippingCountry": "US",
  "OrderItemList": [
    {
      "CatalogID": "XP2001",
      "ItemName": "XP|20 20 Flexible Roller Conveyor Table",
      "ItemQuantity": 1,
      "ItemUnitPrice": 329.00
    }
  ]
}
```

#### **NetSuite Payload Sent:**
```json
{
  "entity": {
    "id": 12345
  },
  "subsidiary": {
    "id": 1
  },
  "department": {
    "id": 3
  },
  "istaxable": false,
  "tranDate": "2025-08-03",
  "memo": "Order imported from 3DCart - Order #1108412",
  "externalId": "3DCART_1108412",
  "shippingAddress": {
    "addressee": "Logan Williams",
    "attention": "Oak Tree Supplies",
    "addr1": "14110 Plank Street",
    "city": "Fort Wayne",
    "state": "IN",
    "zip": "46818",
    "country": "US"
  },
  "custbodycustbody4": "3DCart Integration",
  "item": {
    "items": [
      {
        "item": {
          "id": 567
        },
        "quantity": 1.0,
        "rate": 329.00,
        "istaxable": false
      },
      {
        "item": {
          "id": 3
        },
        "quantity": 1,
        "rate": 25.00,
        "istaxable": false
      }
    ]
  }
}
```

---

### **4. Complex Order (Tax + Shipping + Discount)**

#### **3DCart Input Data:**
```json
{
  "OrderID": "1108413",
  "CustomerID": "341",
  "OrderDate": "2025-08-03 13:00:00",
  "OrderTotal": 371.85,
  "SalesTax": 27.85,
  "ShippingCost": 25.00,
  "DiscountAmount": 10.00,
  "BillingFirstName": "Logan",
  "BillingLastName": "Williams",
  "BillingEmail": "lwilliams@oaktreesupplies.com",
  "QuestionList": [
    {
      "QuestionID": 2,
      "QuestionAnswer": "PO-2025-001"
    }
  ],
  "ShipmentList": [
    {
      "ShipmentFirstName": "Logan",
      "ShipmentLastName": "Williams",
      "ShipmentCompany": "Oak Tree Supplies",
      "ShipmentPhone": "(260) 555-0123"
    }
  ],
  "ShippingAddress": "14110 Plank Street",
  "ShippingCity": "Fort Wayne",
  "ShippingState": "IN",
  "ShippingZipCode": "46818",
  "ShippingCountry": "US",
  "OrderItemList": [
    {
      "CatalogID": "XP2001",
      "ItemName": "XP|20 20 Flexible Roller Conveyor Table",
      "ItemQuantity": 1,
      "ItemUnitPrice": 329.00
    }
  ]
}
```

#### **NetSuite Payload Sent:**
```json
{
  "entity": {
    "id": 12345
  },
  "subsidiary": {
    "id": 1
  },
  "department": {
    "id": 3
  },
  "istaxable": false,
  "tranDate": "2025-08-03",
  "memo": "Order imported from 3DCart - Order #1108413",
  "externalId": "3DCART_1108413",
  "otherrefnum": "PO-2025-001",
  "shippingAddress": {
    "addressee": "Logan Williams",
    "attention": "Oak Tree Supplies",
    "addrphone": "(260) 555-0123",
    "addr1": "14110 Plank Street",
    "city": "Fort Wayne",
    "state": "IN",
    "zip": "46818",
    "country": "US"
  },
  "custbodycustbody4": "3DCart Integration",
  "item": {
    "items": [
      {
        "item": {
          "id": 567
        },
        "quantity": 1.0,
        "rate": 329.00,
        "istaxable": false
      },
      {
        "item": {
          "id": 2
        },
        "quantity": 1,
        "rate": 27.85,
        "istaxable": false
      },
      {
        "item": {
          "id": 3
        },
        "quantity": 1,
        "rate": 25.00,
        "istaxable": false
      },
      {
        "item": {
          "id": 4
        },
        "quantity": 1,
        "rate": -10.00,
        "istaxable": false
      }
    ]
  }
}
```

---

### **5. Multi-Item Order**

#### **3DCart Input Data:**
```json
{
  "OrderID": "1108414",
  "CustomerID": "341",
  "OrderDate": "2025-08-03 14:00:00",
  "OrderTotal": 1248.50,
  "SalesTax": 98.50,
  "ShippingCost": 50.00,
  "BillingFirstName": "Logan",
  "BillingLastName": "Williams",
  "BillingEmail": "lwilliams@oaktreesupplies.com",
  "OrderItemList": [
    {
      "CatalogID": "XP2001",
      "ItemName": "XP|20 20 Flexible Roller Conveyor Table",
      "ItemQuantity": 2,
      "ItemUnitPrice": 329.00
    },
    {
      "CatalogID": "XP3001",
      "ItemName": "XP|30 30 Heavy Duty Conveyor",
      "ItemQuantity": 1,
      "ItemUnitPrice": 542.00
    }
  ]
}
```

#### **NetSuite Payload Sent:**
```json
{
  "entity": {
    "id": 12345
  },
  "subsidiary": {
    "id": 1
  },
  "department": {
    "id": 3
  },
  "istaxable": false,
  "tranDate": "2025-08-03",
  "memo": "Order imported from 3DCart - Order #1108414",
  "externalId": "3DCART_1108414",
  "custbodycustbody4": "3DCart Integration",
  "item": {
    "items": [
      {
        "item": {
          "id": 567
        },
        "quantity": 2.0,
        "rate": 329.00,
        "istaxable": false
      },
      {
        "item": {
          "id": 568
        },
        "quantity": 1.0,
        "rate": 542.00,
        "istaxable": false
      },
      {
        "item": {
          "id": 2
        },
        "quantity": 1,
        "rate": 98.50,
        "istaxable": false
      },
      {
        "item": {
          "id": 3
        },
        "quantity": 1,
        "rate": 50.00,
        "istaxable": false
      }
    ]
  }
}
```

---

## 🔧 **PAYLOAD STRUCTURE BREAKDOWN**

### **Required Fields**
```json
{
  "entity": {"id": 12345},           // Customer ID (required)
  "subsidiary": {"id": 1},           // Subsidiary ID (required)
  "department": {"id": 3}            // Department ID (required)
}
```

### **Order Information**
```json
{
  "tranDate": "2025-08-03",          // Order date (YYYY-MM-DD)
  "memo": "Order imported from...",   // Order description
  "externalId": "3DCART_1108410",    // External reference ID
  "otherrefnum": "PO-2025-001",      // Purchase order number (optional)
  "istaxable": false                 // Tax calculation flag
}
```

### **Custom Fields**
```json
{
  "custbodycustbody4": "3DCart Integration"  // Custom field to identify source
}
```

### **Shipping Address**
```json
{
  "shippingAddress": {
    "addressee": "Logan Williams",     // Recipient name
    "attention": "Oak Tree Supplies",  // Company name
    "addrphone": "(260) 555-0123",    // Phone number
    "addr1": "14110 Plank Street",    // Address line 1
    "addr2": "Suite 100",             // Address line 2 (optional)
    "city": "Fort Wayne",             // City
    "state": "IN",                    // State/Province
    "zip": "46818",                   // Postal code
    "country": "US"                   // Country code
  }
}
```

### **Line Items Structure**
```json
{
  "item": {
    "items": [
      {
        "item": {"id": 567},          // NetSuite item ID
        "quantity": 1.0,              // Quantity (float)
        "rate": 329.00,               // Unit price (float)
        "istaxable": false            // Item tax flag
      }
    ]
  }
}
```

---

## 📊 **LINE ITEM TYPES**

### **Product Items**
```json
{
  "item": {"id": 567},               // Product item ID from NetSuite
  "quantity": 2.0,                   // Quantity ordered
  "rate": 329.00,                    // Unit price from 3DCart
  "istaxable": false                 // Based on order tax setting
}
```

### **Tax Items**
```json
{
  "item": {"id": 2},                 // Tax item ID (configured)
  "quantity": 1,                     // Always 1 for tax
  "rate": 27.85,                     // Tax amount from 3DCart
  "istaxable": false                 // Tax items are not taxable
}
```

### **Shipping Items**
```json
{
  "item": {"id": 3},                 // Shipping item ID (configured)
  "quantity": 1,                     // Always 1 for shipping
  "rate": 25.00,                     // Shipping cost from 3DCart
  "istaxable": false                 // Shipping typically not taxable
}
```

### **Discount Items**
```json
{
  "item": {"id": 4},                 // Discount item ID (configured)
  "quantity": 1,                     // Always 1 for discount
  "rate": -10.00,                    // Negative amount for discount
  "istaxable": false                 // Discounts are not taxable
}
```

---

## 🔍 **FIELD MAPPING**

### **3DCart → NetSuite Field Mapping**
| 3DCart Field | NetSuite Field | Type | Notes |
|--------------|----------------|------|-------|
| `OrderID` | `externalId` | String | Prefixed with "3DCART_" |
| `OrderDate` | `tranDate` | Date | Formatted as YYYY-MM-DD |
| `CustomerID` | `entity.id` | Integer | NetSuite customer ID |
| `OrderTotal` | Calculated | Float | Sum of all line items |
| `SalesTax` | Tax line item | Float | Added as separate line |
| `ShippingCost` | Shipping line item | Float | Added as separate line |
| `DiscountAmount` | Discount line item | Float | Added as negative line |
| `QuestionList[QuestionID=2]` | `otherrefnum` | String | Purchase order number |

### **Item Fields Mapping**
| 3DCart Field | NetSuite Field | Type | Notes |
|--------------|----------------|------|-------|
| `CatalogID` | Used for lookup | String | To find NetSuite item ID |
| `ItemQuantity` | `quantity` | Float | Quantity ordered |
| `ItemUnitPrice` | `rate` | Float | Unit price |
| `ItemName` | Used for creation | String | If item doesn't exist |

---

## 🚀 **AUTHENTICATION HEADER**

### **OAuth 1.0 Signature**
```
Authorization: OAuth 
  oauth_consumer_key="your_consumer_key",
  oauth_token="your_token",
  oauth_signature_method="HMAC-SHA256",
  oauth_timestamp="1640995200",
  oauth_nonce="random_string",
  oauth_version="1.0",
  oauth_signature="calculated_signature"
```

---

## 📋 **RESPONSE EXAMPLES**

### **Successful Creation**
```json
{
  "id": "789",
  "tranId": "SO12345",
  "entity": {"id": "12345"},
  "total": 371.85,
  "status": "Pending Fulfillment"
}
```

### **Error Response**
```json
{
  "o:errorDetails": [
    {
      "detail": "Invalid customer reference key 99999.",
      "o:errorCode": "INVALID_KEY_OR_REF",
      "o:errorPath": "entity"
    }
  ]
}
```

---

## 🎯 **KEY DIFFERENCES FROM BEFORE**

### **❌ Before (Incomplete)**
```json
{
  "item": {
    "items": [
      {
        "item": {"id": 567},
        "quantity": 1.0,
        "rate": 329.00
      }
    ]
  }
}
// Missing: Tax, shipping, discount amounts
// Total in NetSuite: $329.00
// Total in 3DCart: $371.85
// Difference: $42.85 missing!
```

### **✅ After (Complete)**
```json
{
  "item": {
    "items": [
      {
        "item": {"id": 567},
        "quantity": 1.0,
        "rate": 329.00
      },
      {
        "item": {"id": 2},
        "quantity": 1,
        "rate": 27.85
      },
      {
        "item": {"id": 3},
        "quantity": 1,
        "rate": 25.00
      },
      {
        "item": {"id": 4},
        "quantity": 1,
        "rate": -10.00
      }
    ]
  }
}
// Includes: Product + Tax + Shipping + Discount
// Total in NetSuite: $371.85
// Total in 3DCart: $371.85
// Perfect match! ✅
```

---

## 🎉 **SUMMARY**

The NetSuite sales order payload now includes:

✅ **Complete Order Information**: All required and optional fields
✅ **Accurate Line Items**: Products, tax, shipping, discounts
✅ **Proper Addressing**: Full shipping address details
✅ **Custom Fields**: Integration identification
✅ **External References**: 3DCart order ID and PO numbers
✅ **Perfect Totals**: Exact match with 3DCart order totals

**The payload structure ensures complete and accurate order data synchronization between 3DCart and NetSuite!** 🚀