=== Event Tickets integration for SliceWP (BETA) ===
Contributors: raphael
Tags: affiliate, affiliate-program, commission, event-tickets, the-events-calendar, slicewp, tickets-commerce, beta
Requires at least: 5.9
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

BETA VERSION - Automatic affiliate commission tracking for The Events Calendar / Event Tickets sales in SliceWP. Requires real-world testing and feedback before v1.0 release.

== Description ==

**IMPORTANT - BETA STATUS:**

This plugin is in BETA (v0.9.0) and needs real-world testing with Event Tickets orders and different site configurations before a stable release.

We are actively collecting feedback to polish before v1.0 release. Please report any issues, edge cases, or performance concerns.

**Event Tickets integration for SliceWP** connects your affiliate program with The Events Calendar's Tickets Commerce gateway. When a customer referred by an affiliate purchases event tickets, a commission is automatically created and tracked in SliceWP.

This plugin bridges two powerful tools:
- **SliceWP**: A complete affiliate program management system
- **The Events Calendar / Event Tickets**: Professional event ticketing with built-in payment processing

= How It Works =

1. **Customer clicks affiliate link** - SliceWP records the referral
2. **Customer purchases tickets** - Event Tickets processes the order
3. **Plugin automatically detects the sale** - Creates a pending commission in SliceWP
4. **Admin approves the order** - Commission becomes ready to pay out
5. **If order is refunded** - Commission is rejected (affiliate doesn't get paid)

The entire process is automatic, auditable, and fully integrated with SliceWP's commission management system.

= Key Features =

*  Automatic commission creation on ticket sales
*  Real-time order status synchronization
*  Refund handling (rejects commissions when orders are refunded)
*  Support for multiple currencies with conversion
*  Per-ticket commission configuration
*  Self-referral prevention (prevents affiliates from commissions on their own purchases)
*  Clickable order reference links in SliceWP
*  Works with SliceWP's commission basis settings (per-order or per-item)
*  Full audit logging

= Requirements =

* WordPress 5.9 or higher
* PHP 7.4 or higher
* **SliceWP 1.2.0 or higher** (required)
* **The Events Calendar** (required)
* **Event Tickets** (required)
* **Tickets Commerce** gateway enabled (not external PayPal/Stripe)

== Installation ==

1. **Download & Upload**
   - Download the plugin from your source
   - Extract to `/wp-content/plugins/slicewp-event-tickets/`
   - Ensure the folder structure is: `/wp-content/plugins/slicewp-event-tickets/slicewp-event-tickets.php`

2. **Activate the Plugin**
   - Log in to WordPress admin
   - Go to **Plugins** - **Installed Plugins**
   - Find "Event Tickets integration for SliceWP"
   - Click **Activate**

3. **Enable the Integration**
   - Go to **SliceWP** - **Settings** - **Integrations**
   - Find "The Events Calendar / Event Tickets" in the list
   - Toggle **Enable**
   - Click **Save Settings**

4. **Verify Installation**
   - Check **SliceWP** - **Settings** - **Logs**
   - Look for "EVENT TICKETS" entries
   - Create a test ticket purchase with an affiliate link
   - Verify commission appears in **SliceWP** - **Commissions**

== How It Works ==

= Commission Lifecycle =

**Pending** (when checkout completes)
- **Unpaid** (when order is marked Completed/Approved)
- **Paid** (when admin pays the affiliate)

Or if refunded:
**Pending** . Then **Rejected** (if order is refunded and setting is enabled)

= Data Collection =

The plugin reads from Event Tickets orders:
- **Purchaser details** (name, email, user ID)
- **Order total** (converted to SliceWP's active currency)
- **Ticket information** (name, quantity, price)
- **Order status** (pending, completed, refunded, voided, etc.)

All data is stored in SliceWP's commission system. Event Tickets order data is never modified.

= Self-Referral Protection =

The plugin automatically prevents affiliates from earning commissions on their own purchases by checking:
- Current user ID (logged-in affiliates)
- Purchaser email address (against affiliate's registered emails)

This can be overridden per-affiliate in SliceWP settings.

= Currency Conversion =

If using multiple currencies:
- Orders are recorded in the order's original currency
- Amounts are converted to SliceWP's active currency
- Conversion rate is logged for auditing

== Configuration ==

= Affiliate Settings (SliceWP) =

**Default Behavior:**
- Affiliates cannot earn commissions on their own ticket purchases
- Commissions are calculated per-ticket (unless set to per-order)
- Refunded orders cause commissions to be rejected (if enabled)

**To Change:**
1. Go to **SliceWP** - **Settings** - **General**
2. Adjust affiliate-wide settings:
   - "Affiliates can commission on own purchases" (checked = allowed)
   - "Reject commissions on refund" (checked = reject on refund)
   - "Commission calculation basis" (per-order or per-item)
   - "Exclude tax from commission" (if desired)

= Per-Ticket Settings =

To disable commissions for specific tickets:

1. Edit the ticket in **Events** - **Tickets**
2. Add post meta:
   - **Meta Key:** `slicewp_disable_commissions`
   - **Meta Value:** `1`
3. Save

Commissions will not be calculated for that ticket.

= Logs =

All commission activity is logged in **SliceWP** - **Settings** - **Logs**. Search for "EVENT TICKETS" to see integration-specific entries.

== Testing ==

Before upgrading to v1.0, please perform these tests:

= Minimum Testing Required =

1. **Basic Commission Creation**
   - Purchase a ticket with an affiliate referral link
   - Verify commission appears in SliceWP with "Pending" status
   - Check SliceWP logs for success messages

2. **Order Completion**
   - Mark the test order as "Completed" in Event Tickets
   - Verify commission status changes to "Unpaid" in SliceWP
   - Check logs confirm the status change

3. **Refund Handling** (if enabled)
   - Create another test order
   - Mark it as "Completed"
   - Mark it as "Refunded"
   - Verify commission status becomes "Rejected"
   - Check logs confirm rejection reason

4. **Self-Referral Prevention**
   - Log in as an affiliate
   - Purchase a ticket while referred by your own link (if possible)
   - Verify no commission is created
   - Check logs show self-referral detected

5. **Currency Conversion** (if multi-currency enabled)
   - Purchase a ticket in a non-default currency
   - Verify amount is converted in SliceWP
   - Check conversion rate is logged

= Optional but Recommended =

- Test with multiple affiliates
- Test with high-volume orders (10+ tickets)
- Test with refunds after payout
- Test with custom commission rates per ticket
- Verify performance under load (check site speed, database query times)
- Test with different payment methods (if Tickets Commerce supports multiple)

= Known Limitations (v0.9) =

- Tax handling is simplified (tax not extracted per-ticket)
- No bulk commission import for existing orders
- Voided/Reversed orders trigger rejection like refunds (may need refinement)

== Frequently Asked Questions ==

= Do I need all three plugins (SliceWP, The Events Calendar, Event Tickets)? =

Yes. The integration requires:
- **SliceWP 1.2+** for affiliate program management
- **The Events Calendar** (free or pro)
- **Event Tickets** (free or pro)
- **Tickets Commerce** gateway for payment processing

If any are missing, the plugin will quietly disable itself without errors.

= Will this work with external payment gateways (PayPal, Stripe)? =

No. This plugin is designed for **Tickets Commerce**, The Events Calendar's built-in payment gateway. For external gateways, commission tracking may not work.

If you're using Stripe or PayPal separately with Event Tickets, you'll need to track commissions manually or use a different integration.

= Can I use this alongside WooCommerce + SliceWP? =

Yes! Absolutely. Each integration uses its own origin marker (`event_tickets` for tickets, `woo` for WooCommerce products), so commissions don't conflict.

= What happens if I deactivate the plugin? =

- All existing commissions are preserved
- SliceWP continues working normally
- Event Tickets continues working normally
- New ticket sales won't create commissions until re-enabled

= Can I delete the plugin after using it? =

Yes. Deleting the plugin folder:
- Does NOT delete existing commissions
- Does NOT delete Event Tickets data
- Does NOT affect SliceWP
- Stops creating commissions for new orders

= How do I test the plugin? =

1. Purchase a test ticket using an affiliate referral link
2. Go to **SliceWP** - **Commissions**
3. Look for a new "Event Tickets" commission with status "Pending"
4. Mark the order as "Completed" in Event Tickets
5. Return to **SliceWP** - **Commissions**
6. Verify the commission status changed to "Unpaid"

If you see "Pending" . Then "Unpaid" transition, the plugin is working.

= Why isn't a commission being created? =

Check **SliceWP** - **Settings** - **Logs** for "EVENT TICKETS" entries. Common reasons:

- **Affiliate not valid** - Affiliate account is inactive or disabled in SliceWP
- **No referrer detected** - Visitor didn't arrive via an affiliate link
- **Commission amount is zero** - Check affiliate commission rates in SliceWP
- **Self-referral detected** - Affiliate is also the purchaser (disable in settings if desired)
- **Duplicate commission** - Commission already created for this order

= Can I customize commission rates per ticket? =

Yes, in SliceWP:
1. Go to **SliceWP** - **Settings** - **Commissions**
2. Set default commission rates
3. **Optionally** (on per-ticket basis) use post meta to override
4. Or configure per-affiliate commission rules

= What currencies are supported? =

All currencies supported by The Events Calendar and SliceWP. Commission amounts are automatically converted to your SliceWP active currency.

= Is this GDPR compliant? =

The plugin:
- Reads only necessary purchaser data (name, email, user ID)
- Stores data in your own WordPress database (not external servers)
- Doesn't use cookies (relies on SliceWP's existing tracking)
- Respects WordPress privacy standards

For full compliance, ensure your privacy policy mentions affiliate tracking and ticketing data collection.

= Does this work with events from multiple venues/organizers? =

Yes. The plugin tracks commissions by ticket/order, regardless of venue or organizer.

= Can I export commission data? =

Yes. Use SliceWP's built-in export features:
1. Go to **SliceWP** - **Commissions**
2. Click **Export** (if available in your SliceWP version)
3. Filter by origin "Event Tickets" if needed

== Support & Feedback ==

This is a BETA release. Please report:

- Bugs or unexpected behavior
- Edge cases (unusual ticket setups, refund scenarios, etc.)
- Performance issues (especially on high-volume sites)
- Feature requests for v1.0
- General feedback and improvement suggestions

For support:
1. Check **SliceWP** - **Settings** - **Logs** for errors
2. Review the **FAQ** section above
3. Verify all requirements are met (SliceWP 1.2+, Event Tickets active, Tickets Commerce enabled)
4. Contact your plugin provider or SliceWP support

For SliceWP-specific questions, visit: https://slicewp.com

For Event Tickets questions, visit: https://theeventscalendar.com/support/

== Changelog ==

= 0.9.0 =
* Initial BETA release
* Full Tickets Commerce integration with SliceWP
* Automatic commission creation, acceptance, and rejection
* Self-referral prevention
* Multi-currency support
* Comprehensive logging
* Zero-downtime installation
* Performance optimizations (batch ticket queries, deduped meta fetches)
* Requires real-world testing before v1.0 release

== Roadmap to v1.0 ==

Planned improvements based on community feedback:

- Per-ticket tax extraction (if Event Tickets exposes it)
- Bulk historical order import
- Commission template system
- Affiliate tier-based commission overrides
- Advanced refund handling options
- Performance benchmarking and optimization
- Full WP CLI support
- GraphQL API support

== License ==

This plugin is licensed under the **GNU General Public License v2 or later**. See https://www.gnu.org/licenses/gpl-2.0.html for details.

== Credits ==

Built to integrate:
- **SliceWP** - https://slicewp.com
- **The Events Calendar** - https://theeventscalendar.com
- **Event Tickets** - Part of The Events Calendar

== Need Help? ==

- **Installation issues?** Check that SliceWP 1.2+, Event Tickets, and Tickets Commerce are all active
- **Commissions not showing?** Check SliceWP logs for "EVENT TICKETS" entries
- **Want to customize?** The plugin exposes filters for developers:
  - `slicewp_referrer_affiliate_id_event_tickets` - Override affiliate detection
  - `slicewp_integration_supports_event_tickets` - Extend integration features

== Version History ==

**Current:** 0.9.0 (BETA - Requires testing)
**Requires:** WordPress 5.9+, PHP 7.4+, SliceWP 1.2+, Event Tickets
**Status:** Beta, Pre-Release
**Next:** 1.0.0 (Stable)

