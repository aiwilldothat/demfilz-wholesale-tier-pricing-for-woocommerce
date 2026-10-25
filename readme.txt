=== Demfilz Wholesale Tier Pricing for WooCommerce ===
Contributors: demfilz
Tags: tier pricing, customer pricing, wholesale, b2b, role based pricing
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Per-customer tier pricing. Create tiers that adjust prices by percentage, fixed amount or exact per-product prices, and assign a tier per customer.

== Description ==

Sell to retail, trade, wholesale and VIP customers from one store, each at their own prices. Demfilz Wholesale Tier Pricing for WooCommerce lets you create any number of named pricing tiers and assign a tier to each customer. While that customer is logged in, every price they see - shop, product page, cart, checkout - uses their tier.

**Three adjustment kinds per tier**

* **Percentage** - a signed percent, e.g. -10 is 10% off, 5 is a 5% surcharge.
* **Fixed amount** - a signed value added to the price, e.g. -5 takes 5.00 off.
* **Specific prices per product** - the tier applies no blanket change; you enter each product's exact tier price on its Tier pricing panel, and products without a price stay at the standard price.

**Assign tiers your way**

* Search-and-assign panel on the Tier Pricing > Customers page.
* Pricing tier column, filter and bulk actions on the Users list.
* Tier dropdown on every user profile.
* Optional role mapping: give a whole role (e.g. a wholesale role) a default tier. A direct per-customer assignment always wins.

**Per-product control**

Each product gets a Tier pricing panel. Percentage and fixed-amount tiers can Inherit the tier rule, be Excluded for that product, or use a Custom rule with its own kind and amount; specific-price tiers show a simple field for that product's exact tier price. Variations follow their parent product.

**Display and safety options**

* Show the standard price struck through next to the tier price.
* Optional badge next to tier prices ("{tier} price").
* Choose whether sale products get the tier adjustment or keep their sale price.
* "Never charge more than the standard price" safety toggle.
* Rounding: none, whole, end in .95, or end in .99.

**Sensible by design**

* Guests and customers without a tier always see standard prices.
* Prices can never drop below zero.
* Orders record which tier priced them; the tier shows on the admin order screen.
* Compatible with WooCommerce High-Performance Order Storage (HPOS).
* No external services, no data leaves your site.

== Frequently Asked Questions ==

= How do I give a customer special prices? =

Create a tier under Tier Pricing > Tiers (for example "Wholesale, percentage, -20"), then assign the customer on Tier Pricing > Customers, the Users list, or their user profile. While logged in they see the adjusted prices everywhere.

= Can a tier increase prices? =

Yes. Positive percentage or fixed amounts act as a surcharge. If you never want a tier to raise a price above the standard price, enable "Never charge more than the standard price" in Settings.

= What happens with products on sale? =

You choose in Settings: apply the tier adjustment to the sale price (default), or skip tier pricing while a product is on sale.

= Can I set a different rule for one product? =

Yes. Every product's edit screen has a Tier pricing panel where each percentage or fixed-amount tier can inherit the tier rule, exclude the product, or use a custom rule (its own kind and amount) just for that product.

= How do specific prices work? =

Create a tier with the "specific prices per product" kind, then open each product and enter its exact price for that tier in the Tier pricing panel. Products you leave empty stay at the standard price. Customers on that tier pay your entered prices.

= Does it work with variable products? =

Yes. Variation prices and the price range shown on variable products are adjusted. Per-product overrides are set on the parent product and apply to all its variations.

= Can I assign a tier to a whole role? =

Yes. Settings has a role map that gives every user of a role a default tier. A direct per-customer assignment always beats the role default.

= Do guests or unassigned customers ever see tier prices? =

No. Tier pricing applies only to logged-in customers that resolve to an active tier.

= Does sorting or filtering by price use the tier prices? =

No. WooCommerce's price sorting and the price filter widget read a lookup table of standard prices, so tiered customers sort and filter by the standard prices. The prices they see and pay are still their tier prices.

= Where do the strikethrough and badge appear? =

On shop, category and product pages for simple products and selected variations. A variable product's price range shows the adjusted range only, and cart, checkout and emails show the plain tier price. External/affiliate products are never adjusted, since their checkout happens on another site at that site's price.

= What about orders an admin creates or edits in wp-admin? =

Orders built or edited in the admin use standard prices on purpose, so an admin's own tier can never leak into someone else's order. Tier prices apply when the customer shops the store themselves.

= Does the plugin send any data to external services? =

No. There are no external calls; everything stays in your WordPress database.

= What happens when I delete a tier? =

Its customers are unassigned and revert to standard prices (or their role default, if one is mapped). Past orders keep their recorded tier name.

== Screenshots ==

1. Tiers page: tier list with plain-English adjustments and the add/edit form.
2. Customers page: search-and-assign panel plus the assignments table.
3. Per-product Tier pricing panel with inherit, exclude and custom rules.
4. Settings: display, behaviour and role mapping.
5. What the customer sees: struck-through standard price, tier price and badge.

== Changelog ==

= 1.0.0 =
* Initial release: unlimited tiers (percentage, fixed amount, specific price), per-customer assignment (Customers page, Users list, profiles), role mapping, per-product overrides, display and rounding options, order tier snapshot, HPOS compatibility.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
