# Meal Plans Wizard Data Enhancement Summary

## Overview
This document outlines all enhancements made to ensure complete meal plan data is collected, stored, displayed in summaries, and sent in email notifications.

## Data Collection & Display

### Meal Plan Wizard Data
The meal plans wizard collects the following data from users:

1. **Plan Details**
   - Plan Type (Individual/Family)
   - Family Size (for family plans, min 2 persons)
   - Meals Per Week (3, 5, 7, 10, or 14)

2. **Meal Selection**
   - Selected meals with quantities
   - Individual pricing for each meal

3. **Delivery Preferences**
   - Delivery Location (with coordinates)
   - Delivery Days (currently Sunday)
   - Preferred Time Slot (Morning, Afternoon, Evening)

## Files Modified

### 1. `/assets/js/meal-plans-wizard.js`
**Lines Updated:** renderSummary() method

**Changes:**
- Enhanced summary rendering to display all collected data in organized sections
- Added "Plan Details" section showing plan type and meals per week
- Added "Delivery Preferences" section with location, days, and time slot
- Added "Meals Summary" section with individual meal breakdown and subtotal
- Improved time slot display with human-readable labels (Morning: 8:00 AM - 12:00 PM, etc.)
- Added meals subtotal calculation

**Impact:** Users now see a comprehensive summary on Step 5 that includes all their choices before submission.

---

### 2. `/templates/emails/restaurant-subscription-created.php`
**Email Template - HTML**

**Changes:**
- Expanded from basic 4-field table to comprehensive multi-section layout
- Added "Plan Overview" section with plan type, meals per week, status, next order date
- Added "Delivery Preferences" section with:
  - Delivery Location
  - Delivery Days
  - Preferred Time Slot (with human-readable labels)
  - Family Size (if applicable)
- Added "Selected Meals" section displaying:
  - Complete list of all selected meals
  - Quantity for each meal
  - Proper table formatting for clarity

**Impact:** Customers receive detailed subscription confirmation emails with all relevant information organized in clear sections.

---

### 3. `/templates/emails/plain/restaurant-subscription-created.php`
**Email Template - Plain Text**

**Changes:**
- Updated from minimal field display to comprehensive plain text layout
- Added separator lines for visual organization
- Expanded "Delivery Preferences" section
- Added "Selected Meals" section with meal names and quantities
- Improved formatting with section headers and line breaks
- Added family size information display

**Impact:** Customers receiving plain text emails get the same comprehensive information in a well-formatted text layout.

---

### 4. `/includes/modules/class-emails-module.php`
**get_subscription_data() Method**

**Changes:**
- Expanded returned data array from 11 fields to 20 fields
- Added extraction of delivery location data (formatted_address, latitude, longitude)
- Added extraction of selected meals array
- Added extraction of delivery days array
- Added family_size field
- Added delivery_time_slot field
- Formatted delivery_days as human-readable string for email display
- Maintained backward compatibility with existing fields

**Fields Added:**
- `delivery_location` - Formatted delivery address string
- `delivery_latitude` - Delivery latitude coordinate
- `delivery_longitude` - Delivery longitude coordinate
- `delivery_days` - Human-readable days string
- `delivery_days_array` - Array of delivery days (raw)
- `delivery_time_slot` - Morning/Afternoon/Evening preference
- `selected_meals` - Array of selected meals with details
- `family_size` - Number of persons for family plans

**Impact:** Email templates can now access complete meal plan data to display comprehensive subscription details.

---

### 5. `/includes/modules/class-subscriptions-module.php`
**Multiple Enhancements:**

#### a. create_subscription_from_meal_plan_order_meta() Method
**Changes:**
- Added column existence checks for family_size and delivery_time_slot
- Updated to capture family_size from selection data
- Updated to capture delivery_time from selection data
- Updated subscription insert statement to include new fields
- Updated bound parameters array to match new fields

**New Fields Stored:**
- `family_size` - Number of persons (default 1)
- `delivery_time_slot` - Time preference (default 'morning')

#### b. New Column Management Methods

**maybe_add_family_size_column()**
- Ensures `family_size` column exists in subscriptions table
- Adds column if missing: `int(11) NOT NULL DEFAULT 1`
- Positioned after `plan_type` column

**maybe_add_delivery_time_slot_column()**
- Ensures `delivery_time_slot` column exists in subscriptions table
- Adds column if missing: `varchar(50) NOT NULL DEFAULT 'morning'`
- Positioned after `delivery_days` column

#### c. Hook Registration Updates (Line ~99-105)
**Changes:**
- Added calls to all column management methods during subscription creation
- Now ensures all necessary columns exist before insertion
- Methods called:
  - `maybe_add_plan_type_column()`
  - `maybe_add_weekly_menu_id_column()`
  - `maybe_add_location_data_column()`
  - `maybe_add_family_size_column()`
  - `maybe_add_delivery_time_slot_column()`

**Impact:** Complete meal plan data is now stored in the database for each subscription.

---

## Database Schema Updates

### Subscriptions Table Enhancements

**New Columns (Auto-created if missing):**
1. `family_size` - INT(11), DEFAULT 1
   - Stores number of persons for family meal plans
   
2. `delivery_time_slot` - VARCHAR(50), DEFAULT 'morning'
   - Stores user's preferred delivery time
   - Values: 'morning', 'afternoon', 'evening'

**Existing Columns Used:**
- `plan_type` - Individual/family designation
- `meals_per_week` - Number of meals per week
- `selected_meals` - JSON array of meals with quantities
- `delivery_days` - JSON array of delivery days
- `location_data` - JSON object with formatted_address, latitude, longitude
- `delivery_location` - Stored in location_data JSON

---

## Data Flow Summary

```
User fills Wizard
    ↓
collectSelectedMeals() gathers meal selections
    ↓
Restaurant State object contains:
  - planType, familySize
  - mealsPerWeek
  - selectedMeals[]
  - deliveryLocation{address, lat, lng}
  - deliveryDays[]
  - deliveryTime
    ↓
renderSummary() displays all data (Step 5)
    ↓
Submit → Order created with meta data
    ↓
Subscription created from order meta
  ↓ (All data stored in DB)
    ↓
Subscriptions created action → Email triggered
    ↓
get_subscription_data() retrieves all stored data
    ↓
Email template renders with complete data
  - HTML version: restaurant-subscription-created.php
  - Plain text version: plain/restaurant-subscription-created.php
```

---

## Email Display Enhancements

### HTML Email (`restaurant-subscription-created.php`)
**Sections:**
1. Plan Overview Table
   - Plan Type (with family size if applicable)
   - Meals per Week
   - Subscription Status
   - Next Order Date

2. Delivery Preferences Table
   - Delivery Location
   - Delivery Days
   - Preferred Time Slot (with readable time ranges)
   - Family Size (if more than 1 person)

3. Selected Meals Table
   - Meal names
   - Quantities for each meal

### Plain Text Email (`plain/restaurant-subscription-created.php`)
**Sections:**
1. Basic subscription info
2. Delivery Preferences section with all details
3. Selected Meals list with quantities
4. Formatted for readability without HTML

---

## Frontend Summary Display

The wizard's Step 5 summary now includes:
- **Plan Details Section**: Plan type and meals per week
- **Delivery Preferences Section**: Location, days, time slot with descriptions
- **Meals Summary Section**: All selected meals with individual pricing and line totals

Time slots display as:
- "Morning (8:00 AM - 12:00 PM)"
- "Afternoon (12:00 PM - 5:00 PM)"
- "Evening (5:00 PM - 9:00 PM)"

---

## Testing Recommendations

1. **Wizard Submission**
   - Test with Individual plan (minimum data)
   - Test with Family plan (with family size)
   - Verify Step 5 summary displays all fields correctly

2. **Database**
   - Verify new columns are created in subscriptions table
   - Check that all data is properly stored as JSON or scalar values

3. **Email Verification**
   - Send test subscription emails
   - Check both HTML and plain text versions
   - Verify all sections render correctly
   - Confirm time slot displays correctly

4. **Data Integrity**
   - Verify meal plan selections are properly stored and retrieved
   - Confirm location data (coords) is persisted
   - Test with various family sizes
   - Test all time slot options

---

## Backward Compatibility

All changes maintain backward compatibility:
- New database columns have sensible defaults
- Existing email templates still function if new data is missing
- The `get_subscription_data()` method returns empty strings/arrays for missing data
- No existing functionality is removed or altered

---

## Future Enhancements

Potential future improvements:
1. Add admin display of all meal plan details on order view page
2. Create meal plan modification endpoint for users to change delivery preferences
3. Add meal plan renewal/pause functionality
4. Display selected meals with product images in email
5. Add pricing breakdown to subscription email
6. Create downloadable PDF summary of subscription details

