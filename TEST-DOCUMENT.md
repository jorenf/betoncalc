# Boost Calculator - Complete End-to-End Test Document

**Plugin Version:** 2.1.12
**Test Document Version:** 1.0
**Date:** February 2026

---

## Table of Contents

1. [Installation & Setup](#1-installation--setup)
2. [Product & Calculator](#2-product--calculator)
3. [Validation & Required Fields](#3-validation--required-fields)
4. [Cart](#4-cart)
5. [Checkout](#5-checkout)
6. [VAT (BTW Verlegd)](#6-vat-btw-verlegd)
7. [Shipping Logic](#7-shipping-logic)
8. [Order Admin](#8-order-admin)
9. [PDF Documents](#9-pdf-documents)
10. [Edge Cases](#10-edge-cases)
11. [Regression Check](#11-regression-check)
12. [Final Acceptance Checklist](#12-final-acceptance-checklist)

---

## How to Use This Document

1. Follow each test step exactly as written
2. Mark each checkbox with the result:
   - `[x]` = PASS
   - `[ ]` = FAIL
3. If FAIL, note the actual behavior in the "Notes" section
4. Complete all sections before final sign-off

---

## 1. Installation & Setup

### 1.1 Fresh Install

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 1.1.1 | Ensure WooCommerce is installed and activated | WooCommerce appears in admin menu | [ ] |
| 1.1.2 | Upload Boost Calculator plugin ZIP via Plugins > Add New > Upload | Plugin uploads without errors | [ ] |
| 1.1.3 | Click "Activate Plugin" | Plugin activates, no PHP errors | [ ] |
| 1.1.4 | Check admin menu | "Boost Calculators" menu appears | [ ] |

**Notes:**
```
_______________________________________________________
```

### 1.2 Required WooCommerce Settings

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 1.2.1 | Go to WooCommerce > Settings > General | Page loads without errors | [ ] |
| 1.2.2 | Set Currency to EUR (€) | Currency saved | [ ] |
| 1.2.3 | Go to WooCommerce > Settings > Tax | Tax settings page loads | [ ] |
| 1.2.4 | Enable taxes | Taxes enabled | [ ] |
| 1.2.5 | Set "Prices entered with tax" to "No, I will enter prices exclusive of tax" | Setting saved | [ ] |
| 1.2.6 | Go to WooCommerce > Settings > Shipping > Shipping Zones | Page loads | [ ] |
| 1.2.7 | Ensure shipping is enabled | Shipping options visible | [ ] |

**Notes:**
```
_______________________________________________________
```

### 1.3 Boost Calculator Settings

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 1.3.1 | Go to Boost Calculators > Modules | Settings page loads | [ ] |
| 1.3.2 | Check "Algemeen" (General) tab | Tab content displays | [ ] |
| 1.3.3 | Enable "BTW Verlegd Module" | Checkbox saves | [ ] |
| 1.3.4 | Enable "Shipping Module" | Checkbox saves | [ ] |
| 1.3.5 | Go to "BTW Verlegd" tab | Tab content displays | [ ] |
| 1.3.6 | Configure VAT labels (optional) | Labels save correctly | [ ] |
| 1.3.7 | Go to "Verzending" (Shipping) tab | Tab content displays | [ ] |
| 1.3.8 | Verify default shipping zones appear (NL, BE, DE) | 10 zones visible | [ ] |
| 1.3.9 | Enable "Afhalen" (Pickup) option | Checkbox saves | [ ] |
| 1.3.10 | Enter pickup address | Address saves | [ ] |

**Notes:**
```
_______________________________________________________
```

### 1.4 Common Misconfiguration Checks

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 1.4.1 | Activate plugin WITHOUT WooCommerce active | Error message or graceful handling | [ ] |
| 1.4.2 | Check PHP error log after activation | No fatal errors | [ ] |
| 1.4.3 | Visit frontend without calculator setup | No PHP errors on product pages | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 2. Product & Calculator

### 2.1 Create Test Calculator

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 2.1.1 | Go to Boost Calculators > Add New | Editor page opens | [ ] |
| 2.1.2 | Enter title "Test Calculator" | Title saves | [ ] |
| 2.1.3 | In sidebar, set "Min Length Input" = 100 | Value saves | [ ] |
| 2.1.4 | Set "Min Length (Price Threshold)" = 1000 | Value saves | [ ] |
| 2.1.5 | Set "Max Length" = 5000 | Value saves | [ ] |
| 2.1.6 | Set "Price per mm" = 0.10 | Value saves | [ ] |
| 2.1.7 | Set "Weight per mm" = 0.005 | Value saves | [ ] |
| 2.1.8 | Add a Color field | Field appears in builder | [ ] |
| 2.1.9 | Add 3 colors: Gray (default), Red (+10%), Blue (+€5) | Colors save | [ ] |
| 2.1.10 | Add a Mitre Angle field with 2 groups | Field appears | [ ] |
| 2.1.11 | Group 1: "Hoek Links" with angles 0°, 45° (+€5), 90° (+€10) | Angles save | [ ] |
| 2.1.12 | Group 2: "Hoek Rechts" with angles 0°, 45° (+€5), 90° (+€10) | Angles save | [ ] |
| 2.1.13 | Save calculator | "Calculator saved" message | [ ] |

**Notes:**
```
_______________________________________________________
```

### 2.2 Create Test Product

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 2.2.1 | Go to Products > Add New | Product editor opens | [ ] |
| 2.2.2 | Enter title "Test Beton Product" | Title saves | [ ] |
| 2.2.3 | Set Regular Price = €50 | Price saves | [ ] |
| 2.2.4 | In "Boost Calculator" meta box, select "Test Calculator" | Calculator selected | [ ] |
| 2.2.5 | Publish product | Product published | [ ] |
| 2.2.6 | View product on frontend | Product page loads | [ ] |

**Notes:**
```
_______________________________________________________
```

### 2.3 Calculator Rendering

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 2.3.1 | View test product page | Calculator form appears below product | [ ] |
| 2.3.2 | Length input field visible | Input shows with default value 100 | [ ] |
| 2.3.3 | Length slider visible | Slider appears below input | [ ] |
| 2.3.4 | Color options visible | Color swatches/options display | [ ] |
| 2.3.5 | Mitre angle options visible | Both groups display | [ ] |
| 2.3.6 | Price display visible | "Berekende Prijs" shows | [ ] |
| 2.3.7 | Weight display visible | "Berekend Gewicht" shows | [ ] |

**Notes:**
```
_______________________________________________________
```

### 2.4 Length Input & Slider Sync

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 2.4.1 | Move slider to 2000 | Input field updates to 2000 | [ ] |
| 2.4.2 | Type 3000 in input field | Slider moves to 3000 | [ ] |
| 2.4.3 | Type 50 (below minimum 100) | Value clamps to 100 | [ ] |
| 2.4.4 | Type 6000 (above maximum 5000) | Value clamps to 5000 | [ ] |
| 2.4.5 | Price updates on length change | Price recalculates | [ ] |

**Notes:**
```
_______________________________________________________
```

### 2.5 Price Calculation

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 2.5.1 | Set length = 1000 (minimum threshold) | Price = €50 (base only) | [ ] |
| 2.5.2 | Set length = 2000 | Price = €50 + (1000 × €0.10) = €150 | [ ] |
| 2.5.3 | Select Red color (+10%) | Price increases by 10% of gray price | [ ] |
| 2.5.4 | Select Blue color (+€5 fixed) | Price increases by €5 | [ ] |
| 2.5.5 | Select 45° mitre on Hoek Links | Price increases by €5 | [ ] |
| 2.5.6 | Select 90° mitre on Hoek Rechts | Price increases by €10 | [ ] |

**Notes:**
```
_______________________________________________________
```

### 2.6 Weight Calculation

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 2.6.1 | Set length = 1000 | Weight = 1000 × 0.005 = 5.000 kg | [ ] |
| 2.6.2 | Set length = 2000 | Weight = 2000 × 0.005 = 10.000 kg | [ ] |
| 2.6.3 | Weight updates in real-time | No page refresh needed | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 3. Validation & Required Fields

### 3.1 Required Color Field

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 3.1.1 | Create calculator with color field set as "Required" | Field saves as required | [ ] |
| 3.1.2 | View product without selecting color | Add to cart should be blocked | [ ] |
| 3.1.3 | Error message displayed | Clear message about required color | [ ] |
| 3.1.4 | Select a color | Add to cart button works | [ ] |

**Notes:**
```
_______________________________________________________
```

### 3.2 Required Mitre Field (KNOWN BUG)

| Step | Action | Expected Result | Actual Behavior | Pass/Fail |
|------|--------|-----------------|-----------------|-----------|
| 3.2.1 | Create calculator with mitre field set as "Required" | Field saves as required | | [ ] |
| 3.2.2 | View product without selecting mitre | Add to cart SHOULD be blocked | | [ ] |
| 3.2.3 | Try to add to cart | Error message SHOULD appear | | [ ] |

**KNOWN BUG:** Mitre required validation may not block add-to-cart like color does.

**Bug Details:**
```
Steps to reproduce:
1. _______________________________________________________
2. _______________________________________________________
3. _______________________________________________________

Expected: _______________________________________________________
Actual: _______________________________________________________
```

### 3.3 Default Mitre Option

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 3.3.1 | Create mitre with "0°" as default | 0° pre-selected on load | [ ] |
| 3.3.2 | Product page shows default selected | Visual indicator on default | [ ] |
| 3.3.3 | Add to cart with default | Works without error | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 4. Cart

### 4.1 Add to Cart

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 4.1.1 | Configure product with length=2000, Red color | Settings visible | [ ] |
| 4.1.2 | Click "Add to Cart" | Success message appears | [ ] |
| 4.1.3 | Go to Cart page | Cart loads with item | [ ] |
| 4.1.4 | Product name visible | "Test Beton Product" shows | [ ] |
| 4.1.5 | Length visible in item details | "Lengte: 2000 mm" shows | [ ] |
| 4.1.6 | Color visible in item details | "Kleur: Red" shows | [ ] |
| 4.1.7 | Mitre visible in item details | Mitre selections show | [ ] |
| 4.1.8 | Weight visible in item details | "Gewicht: X kg" shows | [ ] |

**Notes:**
```
_______________________________________________________
```

### 4.2 Cart Price Verification

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 4.2.1 | Check item price in cart | Matches calculated price from product page | [ ] |
| 4.2.2 | Price is NOT the base product price (€50) | Calculated price shows | [ ] |
| 4.2.3 | Update quantity to 2 | Subtotal doubles | [ ] |
| 4.2.4 | Cart total updates | Total reflects quantity change | [ ] |

**Notes:**
```
_______________________________________________________
```

### 4.3 Cart Weight Verification

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 4.3.1 | Weight visible for calculator item | "Gewicht" label with kg value | [ ] |
| 4.3.2 | Weight used for shipping calculation | Shipping cost reflects weight | [ ] |
| 4.3.3 | Add second item with different length | Total weight accumulates | [ ] |

**Notes:**
```
_______________________________________________________
```

### 4.4 Multiple Items

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 4.4.1 | Add product with length=1000 | Item added | [ ] |
| 4.4.2 | Add same product with length=3000 | Separate cart item created | [ ] |
| 4.4.3 | Each item shows correct configuration | Configurations distinct | [ ] |
| 4.4.4 | Remove one item | Other item remains | [ ] |

**Notes:**
```
_______________________________________________________
```

### 4.5 Oversized Items

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 4.5.1 | Add product with length > 1500mm | Item added | [ ] |
| 4.5.2 | Check shipping cost | Oversized surcharge applied | [ ] |
| 4.5.3 | Surcharge visible in breakdown | Clear indication of surcharge | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 5. Checkout

### 5.1 Shipping Method Selection

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 5.1.1 | Go to Checkout | Checkout page loads | [ ] |
| 5.1.2 | "Bezorging" (Delivery) section visible | Two options: Verzenden/Afhalen | [ ] |
| 5.1.3 | Select "Verzenden" (Shipping) | Shipping option selected | [ ] |
| 5.1.4 | Select "Afhalen" (Pickup) | Pickup address displays | [ ] |
| 5.1.5 | Pickup shows "Gratis" (Free) | €0 for pickup | [ ] |

**Notes:**
```
_______________________________________________________
```

### 5.2 Shipping Cost Display

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 5.2.1 | Enter NL address (postcode 1000-2999) | Zone 1 pricing applies | [ ] |
| 5.2.2 | Shipping cost visible in order summary | Cost displays correctly | [ ] |
| 5.2.3 | Change to NL postcode 6000-9999 | Zone 3 pricing applies (higher) | [ ] |
| 5.2.4 | Shipping cost updates | New cost reflects zone change | [ ] |

**Notes:**
```
_______________________________________________________
```

### 5.3 VAT Display

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 5.3.1 | With business checkbox UNCHECKED | VAT line visible in totals | [ ] |
| 5.3.2 | VAT percentage correct (21% for NL) | Correct percentage applied | [ ] |
| 5.3.3 | Total includes VAT | Subtotal + VAT = Total | [ ] |

**Notes:**
```
_______________________________________________________
```

### 5.4 Business Checkout

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 5.4.1 | Check "Dit is een zakelijke bestelling" | Business fields appear | [ ] |
| 5.4.2 | Company name field visible | "Bedrijfsnaam" field shows | [ ] |
| 5.4.3 | VAT number field visible | "BTW-nummer" field shows | [ ] |
| 5.4.4 | VAT number placeholder shows example | "bijv. NL123456789B01" | [ ] |

**Notes:**
```
_______________________________________________________
```

### 5.5 UI & Layout

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 5.5.1 | Check spacing between elements | Proper visual spacing | [ ] |
| 5.5.2 | No overlapping elements | Clean layout | [ ] |
| 5.5.3 | Labels readable | Text not cut off | [ ] |
| 5.5.4 | Mobile view (resize browser) | Responsive design works | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 6. VAT (BTW Verlegd)

### 6.1 NL Customer + NL VAT

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 6.1.1 | Set country to Netherlands | NL selected | [ ] |
| 6.1.2 | Check business checkbox | Business fields appear | [ ] |
| 6.1.3 | Enter NL VAT number | Number accepted | [ ] |
| 6.1.4 | VAT is APPLIED (not reversed) | 21% VAT in totals | [ ] |
| 6.1.5 | Complete order | Order shows VAT | [ ] |

**Notes:**
```
_______________________________________________________
```

### 6.2 BE Customer + Valid VAT

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 6.2.1 | Set country to Belgium | BE selected | [ ] |
| 6.2.2 | Check business checkbox | Business fields appear | [ ] |
| 6.2.3 | Enter valid BE VAT number | Validation triggers | [ ] |
| 6.2.4 | "BTW-nummer gevalideerd" message | Success message shows | [ ] |
| 6.2.5 | VAT becomes 0% (reversed) | No VAT in totals | [ ] |
| 6.2.6 | "BTW verlegd" text visible | Reverse charge indicated | [ ] |

**Notes:**
```
_______________________________________________________
```

### 6.3 BE Customer + Invalid VAT

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 6.3.1 | Set country to Belgium | BE selected | [ ] |
| 6.3.2 | Check business checkbox | Business fields appear | [ ] |
| 6.3.3 | Enter invalid VAT number | Validation triggers | [ ] |
| 6.3.4 | Error message shown | "BTW-nummer kon niet worden gevalideerd" | [ ] |
| 6.3.5 | VAT is APPLIED (not reversed) | VAT in totals | [ ] |

**Notes:**
```
_______________________________________________________
```

### 6.4 DE Customer + Valid VAT

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 6.4.1 | Set country to Germany | DE selected | [ ] |
| 6.4.2 | Check business checkbox | Business fields appear | [ ] |
| 6.4.3 | Enter valid DE VAT number | Validation triggers | [ ] |
| 6.4.4 | VAT becomes 0% (reversed) | No VAT in totals | [ ] |

**Notes:**
```
_______________________________________________________
```

### 6.5 Business Unchecked

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 6.5.1 | Leave business checkbox UNCHECKED | No business fields | [ ] |
| 6.5.2 | Any country selected | VAT always applied | [ ] |
| 6.5.3 | Complete order | Order has VAT | [ ] |

**Notes:**
```
_______________________________________________________
```

### 6.6 VAT Status in Order

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 6.6.1 | Complete order with VAT reversed | Order created | [ ] |
| 6.6.2 | View order in admin | Order details show | [ ] |
| 6.6.3 | VAT status visible | "BTW verlegd" indicator | [ ] |
| 6.6.4 | VAT number stored | VAT number in order meta | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 7. Shipping Logic

### 7.1 No Address Entered

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 7.1.1 | Go to checkout without address | Checkout loads | [ ] |
| 7.1.2 | Shipping section shows message | "Vul uw adresgegevens in" | [ ] |
| 7.1.3 | No shipping cost calculated | Waiting for address | [ ] |

**Notes:**
```
_______________________________________________________
```

### 7.2 Netherlands Addresses

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 7.2.1 | Enter postcode 1234 (Zone 1) | Zone 1 pricing: €75 euro / €95 blok | [ ] |
| 7.2.2 | Enter postcode 4000 (Zone 2) | Zone 2 pricing: €95 euro / €115 blok | [ ] |
| 7.2.3 | Enter postcode 7000 (Zone 3) | Zone 3 pricing: €125 euro / €150 blok | [ ] |
| 7.2.4 | Delivery days shown | "1-2 werkdagen" etc. | [ ] |

**Notes:**
```
_______________________________________________________
```

### 7.3 Belgium Addresses

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 7.3.1 | Set country BE, postcode 2000 | Zone 4 pricing applies | [ ] |
| 7.3.2 | Set postcode 8500 | Zone 5 pricing applies | [ ] |
| 7.3.3 | Shipping cost higher than NL | BE zones cost more | [ ] |

**Notes:**
```
_______________________________________________________
```

### 7.4 Germany Addresses

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 7.4.1 | Set country DE, postcode 45000 (NRW) | Zone 8 pricing applies | [ ] |
| 7.4.2 | Set postcode 26000 (Niedersachsen) | Zone 9 pricing applies | [ ] |
| 7.4.3 | Set postcode 80000 (rest of DE) | Zone 10 pricing applies | [ ] |

**Notes:**
```
_______________________________________________________
```

### 7.5 Pallet Weight Logic (800kg max)

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 7.5.1 | Add items totaling 500kg | 1 pallet charged | [ ] |
| 7.5.2 | Add items totaling 900kg | 2 pallets charged | [ ] |
| 7.5.3 | Add items totaling 1700kg | 3 pallets charged | [ ] |
| 7.5.4 | Shipping breakdown shows pallet count | "2x pallet (900 kg)" | [ ] |

**Notes:**
```
_______________________________________________________
```

### 7.6 Oversized Surcharge

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 7.6.1 | Add product with length 1400mm | No surcharge | [ ] |
| 7.6.2 | Add product with length 1600mm | Surcharge applied | [ ] |
| 7.6.3 | Surcharge amount visible | €25 fixed (default) | [ ] |

**Notes:**
```
_______________________________________________________
```

### 7.7 Pickup Option

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 7.7.1 | Select "Afhalen" | Pickup selected | [ ] |
| 7.7.2 | Shipping cost = €0 | Free pickup | [ ] |
| 7.7.3 | Pickup address displayed | Address from settings shows | [ ] |
| 7.7.4 | Complete order | Order shows pickup method | [ ] |

**Notes:**
```
_______________________________________________________
```

### 7.8 Cart Updates

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 7.8.1 | Add item, go to checkout | Shipping calculated | [ ] |
| 7.8.2 | Go back to cart, add more items | Cart updates | [ ] |
| 7.8.3 | Return to checkout | Shipping recalculated | [ ] |
| 7.8.4 | Change address | Shipping updates | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 8. Order Admin

### 8.1 Order View

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 8.1.1 | Complete a test order | Order created | [ ] |
| 8.1.2 | Go to WooCommerce > Orders | Order list shows | [ ] |
| 8.1.3 | Click on test order | Order details load | [ ] |

**Notes:**
```
_______________________________________________________
```

### 8.2 Order Details

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 8.2.1 | Product line visible | Product name shows | [ ] |
| 8.2.2 | Calculator config visible | Length, color, mitre in item meta | [ ] |
| 8.2.3 | Weight visible per item | "Gewicht: X kg" shows | [ ] |
| 8.2.4 | Correct item price | Calculated price, not base | [ ] |

**Notes:**
```
_______________________________________________________
```

### 8.3 Shipping Line

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 8.3.1 | Shipping line visible | "Verzending" or "Afhalen" | [ ] |
| 8.3.2 | Correct shipping cost | Matches checkout amount | [ ] |
| 8.3.3 | NO duplicate shipping lines | Only one shipping entry | [ ] |
| 8.3.4 | Correct naming | "Bezorging" not duplicated | [ ] |

**Notes:**
```
_______________________________________________________
```

### 8.4 VAT Line

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 8.4.1 | VAT line visible (if applicable) | Tax line shows | [ ] |
| 8.4.2 | Correct VAT amount | Matches checkout VAT | [ ] |
| 8.4.3 | For reversed VAT: 0% visible | BTW verlegd indicator | [ ] |

**Notes:**
```
_______________________________________________________
```

### 8.5 Totals

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 8.5.1 | Subtotal correct | Sum of items | [ ] |
| 8.5.2 | Shipping correct | Shipping cost | [ ] |
| 8.5.3 | VAT correct | Tax amount | [ ] |
| 8.5.4 | Total correct | All adds up | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 9. PDF Documents

### 9.1 Invoice

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 9.1.1 | Go to order in admin | Order loads | [ ] |
| 9.1.2 | Find "Download Invoice" button | Button visible | [ ] |
| 9.1.3 | Click to download | PDF downloads | [ ] |
| 9.1.4 | Open PDF | Readable layout | [ ] |
| 9.1.5 | Product details visible | Name, price, quantity | [ ] |
| 9.1.6 | Calculator config visible | Length, color, mitre | [ ] |
| 9.1.7 | VAT displayed correctly | Amount or "BTW verlegd" | [ ] |
| 9.1.8 | Reverse charge text (if applicable) | Text visible on invoice | [ ] |
| 9.1.9 | Totals correct | All amounts accurate | [ ] |

**Notes:**
```
_______________________________________________________
```

### 9.2 Packing Slip

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 9.2.1 | Find "Download Packing Slip" button | Button visible | [ ] |
| 9.2.2 | Click to download | PDF downloads | [ ] |
| 9.2.3 | Open PDF | Readable layout | [ ] |
| 9.2.4 | Product name visible | Clear product identification | [ ] |
| 9.2.5 | Length visible | "Lengte: X mm" | [ ] |
| 9.2.6 | Color visible | Color name shown | [ ] |
| 9.2.7 | Mitre visible | Mitre selections shown | [ ] |
| 9.2.8 | NO pricing on packing slip | No prices (for warehouse use) | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 10. Edge Cases

### 10.1 Empty Cart

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 10.1.1 | Go to cart with no items | "Cart is empty" message | [ ] |
| 10.1.2 | No PHP errors | Clean empty state | [ ] |
| 10.1.3 | No shipping displayed | N/A for empty cart | [ ] |

**Notes:**
```
_______________________________________________________
```

### 10.2 Cart Updates

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 10.2.1 | Add item to cart | Item added | [ ] |
| 10.2.2 | Change quantity in cart | Price updates | [ ] |
| 10.2.3 | Remove item | Item removed | [ ] |
| 10.2.4 | Add same product again | New item created | [ ] |

**Notes:**
```
_______________________________________________________
```

### 10.3 Address Change Mid-Checkout

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 10.3.1 | Start checkout with NL address | NL shipping shown | [ ] |
| 10.3.2 | Change country to BE | Shipping recalculates | [ ] |
| 10.3.3 | Change back to NL | Shipping recalculates again | [ ] |
| 10.3.4 | No errors during changes | Smooth transitions | [ ] |

**Notes:**
```
_______________________________________________________
```

### 10.4 Large Quantities

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 10.4.1 | Add product with quantity 100 | Item added | [ ] |
| 10.4.2 | Price calculates correctly | 100 × item price | [ ] |
| 10.4.3 | Weight calculates correctly | 100 × item weight | [ ] |
| 10.4.4 | Pallet calculation correct | Multiple pallets if needed | [ ] |

**Notes:**
```
_______________________________________________________
```

### 10.5 Invalid Input

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 10.5.1 | Enter negative length | Value rejected or clamped | [ ] |
| 10.5.2 | Enter text in length field | Input sanitized | [ ] |
| 10.5.3 | Submit form with missing data | Validation error shown | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 11. Regression Check

### 11.1 Non-Calculator Products

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 11.1.1 | Create standard WooCommerce product | Product created | [ ] |
| 11.1.2 | DO NOT assign calculator | No calculator meta | [ ] |
| 11.1.3 | View product page | Normal product page | [ ] |
| 11.1.4 | Add to cart | Works normally | [ ] |
| 11.1.5 | Complete checkout | Order successful | [ ] |

**Notes:**
```
_______________________________________________________
```

### 11.2 PHP Errors

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 11.2.1 | Enable WP_DEBUG in wp-config.php | Debug mode on | [ ] |
| 11.2.2 | Browse product pages | No PHP errors | [ ] |
| 11.2.3 | Add to cart | No PHP errors | [ ] |
| 11.2.4 | Complete checkout | No PHP errors | [ ] |
| 11.2.5 | View orders in admin | No PHP errors | [ ] |

**Notes:**
```
_______________________________________________________
```

### 11.3 JavaScript Errors

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 11.3.1 | Open browser console (F12) | Console visible | [ ] |
| 11.3.2 | Load product page | No JS errors | [ ] |
| 11.3.3 | Interact with calculator | No JS errors | [ ] |
| 11.3.4 | Go through checkout | No JS errors | [ ] |
| 11.3.5 | Load admin pages | No JS errors | [ ] |

**Notes:**
```
_______________________________________________________
```

### 11.4 Console Warnings

| Step | Action | Expected Result | Pass/Fail |
|------|--------|-----------------|-----------|
| 11.4.1 | Check console for warnings | Minimal warnings | [ ] |
| 11.4.2 | No deprecation warnings from plugin | Clean console | [ ] |

**Notes:**
```
_______________________________________________________
```

---

## 12. Final Acceptance Checklist

### Core Features

| Feature | Working | Notes |
|---------|---------|-------|
| Calculator renders on product page | [ ] | |
| Length input works | [ ] | |
| Slider syncs with input | [ ] | |
| Color selection works | [ ] | |
| Mitre selection works | [ ] | |
| Price calculates correctly | [ ] | |
| Weight calculates correctly | [ ] | |
| Add to cart works | [ ] | |
| Cart shows correct data | [ ] | |
| Cart shows calculated price (not base) | [ ] | |
| Cart shows weight | [ ] | |
| Checkout loads | [ ] | |
| Shipping selection works | [ ] | |
| Pickup option works | [ ] | |
| VAT calculation works | [ ] | |
| VAT reverse charge works | [ ] | |
| Order admin shows all data | [ ] | |
| Invoice PDF works | [ ] | |
| Packing slip PDF works | [ ] | |

### Known Issues

| Issue | Severity | Status |
|-------|----------|--------|
| Mitre required validation may not block add-to-cart | Medium | Needs verification |
| | | |
| | | |

### Blocking Bugs Found During Testing

| Bug Description | Steps to Reproduce | Severity |
|-----------------|-------------------|----------|
| | | |
| | | |
| | | |

### Non-Blocking Issues Found

| Issue | Description | Priority |
|-------|-------------|----------|
| | | |
| | | |
| | | |

---

## Final Verdict

### Summary

- **Total Tests:** ___
- **Passed:** ___
- **Failed:** ___
- **Blocking Issues:** ___

### Sign-Off

```
Tested By: _______________________________
Date: _______________________________
Environment: _______________________________
Browser: _______________________________
```

### Final Decision

- [ ] **APPROVED FOR PRODUCTION**
  - All critical features working
  - No blocking bugs
  - Acceptable UI/UX
  - Calculations correct

- [ ] **NEEDS FIXES**
  - List blocking issues below:

  1. _______________________________________________________
  2. _______________________________________________________
  3. _______________________________________________________
  4. _______________________________________________________
  5. _______________________________________________________

### Signatures

```
QA Tester: _______________________________ Date: ___________

Developer: _______________________________ Date: ___________

Project Manager: _________________________ Date: ___________
```

---

## Appendix: Test Data Reference

### Default Shipping Zone Prices (for verification)

| Zone | Region | Euro Pallet | Blok Pallet |
|------|--------|-------------|-------------|
| 1 | NL Noord/Zuid-Holland (1000-2999) | €75 | €95 |
| 2 | NL Utrecht/Gelderland/Brabant (3000-5999) | €95 | €115 |
| 3 | NL Rest (6000-9999) | €125 | €150 |
| 4 | BE Antwerpen/Limburg (2000-3999) | €150 | €175 |
| 5 | BE Oost/West-Vlaanderen (8000-9999) | €175 | €200 |
| 6 | BE Brussel/Waals-Brabant (1000-1999, 6000-7999) | €175 | €200 |
| 7 | BE Namen/Luik/Luxemburg (4000-5999) | €200 | €225 |
| 8 | DE NRW (40000-48999, 50000-53999, 57000-59999) | €175 | €200 |
| 9 | DE Niedersachsen/Bremen | €200 | €225 |
| 10 | DE Rest | €250 | €285 |

### Calculation Formulas

**Price:**
```
Base Price (for min_length) +
Length Extra (if length > min_length) +
Color Surcharge (fixed or % of gray price) +
Mitre Surcharges +
Custom Field Surcharges
```

**Weight:**
```
Product Base Weight +
(Length × Weight per mm) +
Mitre Extra Weight +
Custom Field Extra Weight
```

**Pallets Needed:**
```
ceil(Total Weight / 800)
```

---

*End of Test Document*
