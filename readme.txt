=== Moonetix Offerwall ===
Contributors: moonetixcom
Tags: offerwall, rewards, points, monetization, woocommerce
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show the Moonetix offerwall to logged-in users and credit their rewards automatically: built-in balance, myCRED, GamiPress, or TeraWallet.

== Description ==

Moonetix Offerwall puts a rewarded offerwall on your WordPress site. Your users complete offers such as app installs, games, and sign-ups, they get points on your site, and you earn for every completed offer.

The plugin does the parts that are easy to get wrong:

* **Signed offerwall links.** The link of every user is signed on your server with your link hash salt, so nobody can open the offerwall as someone else.
* **Safe reward crediting.** Moonetix sends a postback for every reward and every reversal. The plugin checks its HMAC-SHA256 hash, credits each transaction only once, and takes a reward back only if it was credited before.
* **Your points system, or your shop.** Credit rewards to a built-in balance, to [myCRED](https://wordpress.org/plugins/mycred/), to [GamiPress](https://wordpress.org/plugins/gamipress/), or to the WooCommerce wallet of [TeraWallet](https://wordpress.org/plugins/woo-wallet/), where users spend them at checkout. You set how many points make 1 unit of your store currency.
* **Who can earn.** Every logged-in user, or only the roles you choose, for example paying members or customers. Everyone else sees your own message.
* **Blocks and shortcodes.** The Moonetix Offerwall, Moonetix Balance, and Moonetix Reward History blocks, or `[moonetix_offerwall]`, `[moonetix_balance]`, and `[moonetix_history]`.
* **A floating Earn button.** Optional button with the user's balance in the corner of every page. It opens the offerwall in a window, so users find it from anywhere. Show it on all devices, only on phones, or only on computers, and hide it on the pages you choose and on the cart and checkout.
* **Your own menu and buttons.** Add the CSS class `moonetix-ow-open` to a menu item or a button block, and it opens the offerwall in a window.
* **Reward notices.** After a reward, the user sees a short "+250 Coins" notice on the next page.
* **A postback log with search.** The Postbacks tab lists every postback with the offer, country, device, reward, your earnings, and what the plugin did with it. Search by user, email, transaction ID, or offer.
* **Balance changes.** With the built-in balance, change a user's balance on their profile, for example when you pay them out. The user sees the change and the reason.
* **Share calculator.** See what your users get and what you keep for an offer, with the conversion rate and split from your Moonetix dashboard.
* **Setup check.** A status box at the top of the settings checks your keys, HTTPS, your points plugin, and whether Moonetix can reach your postback URL.
* **Privacy tools.** The reward history is part of the WordPress personal data export and erase tools.
* **Ready-made Earn page.** One click creates a page with the balance, the offerwall, and the reward history. There is also an "Earn page" block pattern.
* **Built into WordPress.** The setup check shows up in Tools, Site Health, and a small widget on the dashboard shows the rewards of the last 7 days.

= Built for safety =

* Offerwall links are signed with HMAC-SHA256 for the logged-in user, so nobody can earn as someone else.
* Postbacks are verified with HMAC-SHA256 and a constant-time comparison before anything is written.
* Each transaction is credited once, even when a postback arrives twice at the same moment, and a reversal only takes back a reward that was credited.
* Keys are never shown after they are saved, and can be kept in wp-config.php instead of the database.
* Pages with a user's signed link or balance are never stored by page caches.
* No tracking, no ads, and no calls to Moonetix from your server. The plugin only answers the postbacks Moonetix sends.
* No links on your site unless you turn them on. The optional "Rewards by Moonetix" link under the offerwall is off by default.

Visitors who are not logged in see a message with a login link instead of the offerwall, because rewards need an account to land in.

You need a free Moonetix publisher account and an approved offerwall for your website. [Sign up at moonetix.com](https://moonetix.com/).

= External services =

This plugin uses the Moonetix offerwall service (https://moonetix.com/). It cannot work without it.

* **The offerwall.** When a logged-in user opens a page with the offerwall, their browser loads it from https://wall.moonetix.com/. The request contains your App ID, the user's ID on your site, and a signature, plus what any browser sends to a website it opens. Nothing is loaded for visitors who are not logged in.
* **Postbacks.** When a reward is confirmed or reversed, Moonetix sends the details of that reward to this plugin on your site. The plugin itself sends no data to Moonetix.
* **Optional credit link.** Only if you turn it on, a "Rewards by Moonetix" link is shown under the offerwall. It is off by default.

Moonetix terms of service: https://moonetix.com/terms
Moonetix privacy policy: https://moonetix.com/privacy

= Support =

The setup guide at https://docs.moonetix.com/wordpress covers every step and setting. For help, use the live chat in your Moonetix dashboard, the contact form at https://moonetix.com/contact, write to partners@moonetix.com, or ask in the support forum of this plugin. For your Moonetix account, offers, and payments, use your Moonetix dashboard.

== Installation ==

1. Install and activate the plugin.
2. In your Moonetix dashboard, create an offerwall for your website.
3. Go to Settings, Moonetix Offerwall, and copy three values from your Moonetix dashboard: the App ID (Offerwalls, Copy ID next to your offerwall), the link hash salt (your offerwall, Integration, 2. Link security), and the postback secret key (Postback in the menu). Each field in the settings says exactly where to find it.
4. Choose where rewards go: the built-in balance, myCRED, GamiPress, or TeraWallet. Optionally, choose who can earn and how the Earn button shows.
5. Copy the postback URL from the settings page and paste it into the Postback URL field of your offerwall on the Postback page of your Moonetix dashboard. Open Send a test postback there and send one and check that it shows up on the Postbacks tab, and that the status box says "Everything is set up".
6. Click "Create the Earn page" in the settings, check the draft, and publish it. Or add the Moonetix Offerwall block, or the `[moonetix_offerwall]` shortcode, to any page.

== Frequently Asked Questions ==

= Is it free? =

Yes. The plugin is free, and joining Moonetix as a publisher is free. Moonetix pays you for every offer your users complete.

= How much do my users get, and how much do I keep? =

You choose it in your Moonetix dashboard, under your offerwall, Rewards: the conversion rate (how many points make $1 of your earnings) and the split to user (the share in percent your users get). The rest is yours. The calculator under Settings, Rewards shows the result for any offer before you change anything.

= Can rewards go to the WooCommerce wallet? =

Yes. Install TeraWallet, choose it under Settings, Rewards, and enter how many points make 1 unit of your store currency, for example 1,000 points for 1 EUR. Rewards are added to the wallet as money, and users spend them at checkout. A reversed reward is taken back from the wallet.

= Can I show the offerwall to members only? =

Yes. Under Who can earn, choose the roles that may earn, for example a membership role or Customer. Everyone else sees your message instead. Administrators always see the offerwall, so they can test it.

= Can I open the offerwall from my own menu? =

Yes. Turn on menu links under Display, then add the CSS class moonetix-ow-open to a menu item, or to a button or navigation link block. A click opens the offerwall in a window.

= Can I hide the Earn button on some pages? =

Yes. Choose the pages in the settings. It is also hidden on pages that already show the offerwall, and, with WooCommerce, on the cart, the checkout, and My account.

= Does it work with my theme? =

Yes. The offerwall loads in its own frame, so it looks the same in every theme. You set its colors, your currency name, and its icon in your Moonetix dashboard.

= Do I need a Moonetix account? =

Yes. The offers and the payments come from Moonetix. Signing up is free at https://moonetix.com/.

= Which user ID does Moonetix get? =

The WordPress user ID of the logged-in user. Developers can change it with the `moonetix_offerwall_user_id` filter, and map it back in postbacks with the `moonetix_offerwall_postback_user` filter.

= A reward was reversed after the user spent it. What happens? =

The plugin takes the reward back anyway, so the balance can go below zero. myCRED and GamiPress handle this the way they are configured.

= Why does GamiPress round the rewards? =

GamiPress counts whole points. Set your Moonetix currency to whole numbers, for example 1,000 points per dollar, and nothing is lost to rounding.

= Can I use the same offerwall on a staging and a live site? =

Use a separate offerwall, with its own keys, for each site. A postback signed for one site is then never accepted by the other.

= Where can I keep the keys outside the database? =

Define MOONETIX_OFFERWALL_LINK_SALT and MOONETIX_OFFERWALL_POSTBACK_KEY in wp-config.php. The settings page then shows them as set in wp-config.php and never stores them.

= The offerwall shows "VPN detected" when I test it. =

Offers are not shown over VPNs or proxies. Test from a normal home or mobile connection.

= Does it work with page caching? =

Yes. Pages that show a logged-in user's offerwall, balance, history, floating button, or notices tell browsers and page cache plugins that respect DONOTCACHEPAGE not to store them. If your CDN caches every page, bypass the cache for logged-in users.

= Who pays my users? =

You do. The plugin credits and tracks the points. How users redeem them, for example cash, gift cards, or store coupons, is up to you: use the redeem features of myCRED or GamiPress, or pay users yourself and take the points away on their profile. Moonetix pays you for the completed offers.

= Can my users see their rewards? =

Yes. Put `[moonetix_history]` on their account page. It lists their rewards, and shows reversed ones struck through.

= What happens if myCRED or GamiPress is switched off? =

The plugin does not credit anything and answers the postback with an error, so Moonetix sends it again later. The settings page shows a warning, so you know to switch it back on.

= What is the "Rewards by Moonetix" link? =

An optional link under the offerwall, off by default. Turn it on and add your Moonetix referral code, and you earn a share of what the publishers who sign up through it earn. It uses rel="nofollow".

= What is deleted when I delete the plugin? =

Nothing, unless you tick "Delete all plugin data when the plugin is deleted" under Settings, Data first.

== Screenshots ==

1. The offerwall on a page, with the floating Earn button and a reward notice.
2. The floating Earn button opens the offerwall in a window.
3. Settings: the status box, the setup steps, and the share calculator.
4. Postbacks: every postback with its result, searchable by user, transaction, or offer.
5. A user's balance on their profile, with a manual change for payouts.
6. On a phone.

== Changelog ==

= 1.0.0 =
* First release.
* Offerwall, balance, and reward history blocks and shortcodes, with signed offerwall links.
* Postback endpoint with HMAC-SHA256 check and once-per-transaction crediting, to a built-in balance, myCRED, or GamiPress.
* Floating Earn button, reward notices, and a one-click Earn page with a block pattern.
* Postbacks tab with search, balance changes on user profiles, setup check, Site Health test, and dashboard widget.
* Privacy export and erase, keys in wp-config.php, and an optional "Rewards by Moonetix" link.
* TeraWallet (WooCommerce wallet) as a place for rewards, with your own points-to-currency rate.
* Who can earn: all logged-in users or chosen roles, with your own message for everyone else.
* Earn button on chosen devices, hidden on chosen pages and on the cart and checkout; menu items and buttons that open the offerwall.
* Share calculator for the conversion rate and split to user.

== Upgrade Notice ==

= 1.0.0 =
First release.
