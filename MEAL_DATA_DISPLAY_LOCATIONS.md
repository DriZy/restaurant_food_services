# Meal Plans Data Display Locations

## Complete Reference of Where All Collected Data is Displayed

### 1. Frontend Wizard Summary (Step 5)
**File:** `/assets/js/meal-plans-wizard.js` (renderSummary method)

**What's Displayed:**
- ✅ Plan Type (Individual/Family with family size if applicable)
- ✅ Meals Per Week
- ✅ Delivery Location (formatted address)
- ✅ Delivery Days (e.g., "Sunday")
- ✅ Preferred Time Slot (with readable time range)
- ✅ Selected Meals List
  - Meal name
  - Unit price
  - Quantity
  - Line total
- ✅ Meals Subtotal (sum of all meal items)

**Visibility:** Customers see this before submission as final confirmation

---

### 2. Subscription Email - HTML Version
**File:** `/templates/emails/restaurant-subscription-created.php`

**What's Displayed:**

#### Plan Overview Table
- ✅ Plan Type (with "Family Plan" label if family_size > 1)
- ✅ Meals per Week
- ✅ Status (Active/Inactive)
- ✅ Next Order Date

#### Delivery Preferences Table
- ✅ Delivery Location (formatted address)
- ✅ Delivery Days (e.g., "Sunday")
- ✅ Preferred Time Slot (with time ranges)
- ✅ Family Size (if 2+ persons)

#### Selected Meals Table
- ✅ Meal names
- ✅ Quantities for each meal

**Visibility:** Sent to customer email when subscription is created

---

### 3. Subscription Email - Plain Text Version
**File:** `/templates/emails/plain/restaurant-subscription-created.php`

**What's Displayed:**
- ✅ Plan Type (Individual/Family)
- ✅ Meals per Week
- ✅ Status
- ✅ Next Order Date
- ✅ Family Size (if applicable)
- ✅ Delivery Location
- ✅ Delivery Days
- ✅ Preferred Time Slot
- ✅ Selected Meals (name x quantity format)

**Visibility:** Plain text email version for users with text-only email clients

---

### 4. Database - Subscriptions Table
**Table:** `wp_restaurant_subscriptions`

**Data Stored:**
- `plan_type` - 'individual' or 'family'
- `family_size` - Integer (1 for individual, 2+ for family)
- `meals_per_week` - Integer
- `selected_meals` - JSON array of meals with product IDs and quantities
- `delivery_days` - JSON array of days
- `delivery_time_slot` - 'morning', 'afternoon', or 'evening'
- `location_data` - JSON object with:
  - formatted_address
  - latitude
  - longitude

**Columns Added by This Enhancement:**
1. `family_size` - Stores family meal plan size
2. `delivery_time_slot` - Stores user's preferred delivery time

---

### 5. Email Data Processing
**File:** `/includes/modules/class-emails-module.php`

**Method:** `get_subscription_data()`

**Data Fields Available to Email Templates:**
```
Returns array with:
- subscription_id
- plan_id
- plan_name
- plan_type [NEW]
- meals_per_week
- family_size [NEW]
- user_id
- user_email
- user_name
- status
- next_order_date
- created_at
- delivery_location [NEW]
- delivery_latitude [NEW]
- delivery_longitude [NEW]
- delivery_days [NEW]
- delivery_days_array [NEW]
- delivery_time_slot [NEW]
- selected_meals [NEW]
```

---

## Data Collection Flow

```
STEP 1: Choose Plan
  ↓ Collects: planType, familySize

STEP 2: Meals Per Week
  ↓ Collects: mealsPerWeek

STEP 3: Choose Meals
  ↓ Collects: selectedMeals[] with product_id, quantity, name, price

STEP 4: Delivery Preferences
  ↓ Collects:
    - deliveryLocation (formattedAddress, latitude, longitude)
    - deliveryDays
    - deliveryTime

STEP 5: Summary
  ↓ DISPLAYS ALL COLLECTED DATA
    - Plan Details section
    - Delivery Preferences section
    - Meals Summary section

SUBMIT
  ↓
Order Created with Order Meta
  ↓
Subscription Created from Order Meta
  ↓
DATA STORED IN SUBSCRIPTIONS TABLE
  ↓
Subscription Created Action Triggered
  ↓
Email Handler Called
  ↓
get_subscription_data() Retrieves All Data
  ↓
EMAIL TEMPLATES RENDER WITH COMPLETE DATA
  - HTML version: restaurant-subscription-created.php
  - Plain text version: plain/restaurant-subscription-created.php
```

---

## Summary of Display Locations

| Data Point | Step 5 Summary | HTML Email | Plain Email | Database |
|-----------|----------------|-----------|-----------|---------​|
| Plan Type | ✅ | ✅ | ✅ | ✅ |
| Family Size | ✅ | ✅ | ✅ | ✅ |
| Meals Per Week | ✅ | ✅ | ✅ | ✅ |
| Meals List | ✅ | ✅ | ✅ | ✅ |
| Meal Quantities | ✅ | ✅ | ✅ | ✅ |
| Meal Prices | ✅ | ⛔ | ⛔ | ✅ |
| Meals Subtotal | ✅ | ⛔ | ⛔ | ✅ |
| Delivery Location | ✅ | ✅ | ✅ | ✅ |
| Delivery Days | ✅ | ✅ | ✅ | ✅ |
| Delivery Time Slot | ✅ | ✅ | ✅ | ✅ |
| Latitude/Longitude | ⛔ | ⛔ | ⛔ | ✅ |

---

## Next Steps to Consider

### Display on Account Page
The account/my-account page could show meal plan subscriptions with this data through a custom template or account endpoint. This would allow customers to view their subscription details from their dashboard.

### Admin Order Pages
Admin could see this data by adding custom meta display fields on the WooCommerce order edit page.

### Renewal Order Creation
When renewal orders are created, all this data should be preserved and displayed similarly.

### Modification Endpoint
A future enhancement could allow customers to modify their subscription preferences (delivery location, time, days, meal selections).

---

## Data Validation

The following validations are already in place:

- **Plan Type:** Only 'individual' or 'family' allowed
- **Family Size:** Minimum 2 for family plans, must be integer
- **Meals Per Week:** Options: 3, 5, 7, 10, 14
- **Delivery Days:** Currently only Sunday, but stored as array
- **Delivery Time:** Only 'morning', 'afternoon', 'evening' allowed
- **Delivery Location:** Must have formatted_address, latitude, longitude
- **Selected Meals:** Must have at least one meal with quantity > 0
- **Product Data:** Meals must be purchasable and in stock

---

## Email Template Variables Reference

For developers modifying email templates:

```php
// Available in both HTML and plain text templates:
$subscription->plan_type           // 'individual' or 'family'
$subscription->family_size         // Integer (1+)
$subscription->meals_per_week      // Integer
$subscription->delivery_location   // Formatted address string
$subscription->delivery_days       // Comma-separated days string
$subscription->delivery_days_array // Array of days
$subscription->delivery_time_slot  // 'morning'|'afternoon'|'evening'
$subscription->selected_meals      // Array of meal objects
$subscription->status              // 'active'|'inactive'
$subscription->next_order_date     // Date string
$subscription->plan_name           // Generated or custom plan name
```

### Accessing Selected Meals Array

```php
$meals_list = is_string($subscription->selected_meals) 
    ? json_decode($subscription->selected_meals, true) 
    : $subscription->selected_meals;

// Each meal item contains:
$meal_item['product_id']     // Product ID
$meal_item['quantity']       // Quantity ordered
$meal_item['product_name']   // Meal name (if available)
$meal_item['name']           // Alternative meal name field
```

---

