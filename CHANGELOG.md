# Changelog

## [4.3.0](https://github.com/ellite/Wallos/compare/v4.2.0...v4.3.0) (2025-09-15)


### Features

* add health endpoint and healthcheck to container ([#919](https://github.com/ellite/Wallos/issues/919)) ([852cb48](https://github.com/ellite/Wallos/commit/852cb485a65a58c91577b369fb9ea293d370bda8))

## [4.2.0](https://github.com/ellite/Wallos/compare/v4.1.1...v4.2.0) (2025-09-14)


### Features

* add pushplus notification service  ([#911](https://github.com/ellite/Wallos/issues/911)) ([27ac805](https://github.com/ellite/Wallos/commit/27ac805141c0d170a40c2a7796a589a5ef29544f))
* make container shutdown instant & graceful ([27ac805](https://github.com/ellite/Wallos/commit/27ac805141c0d170a40c2a7796a589a5ef29544f))
* make container shutdown instant & graceful  ([#916](https://github.com/ellite/Wallos/issues/916)) ([27ac805](https://github.com/ellite/Wallos/commit/27ac805141c0d170a40c2a7796a589a5ef29544f))
* option to delete ai recommendations ([27ac805](https://github.com/ellite/Wallos/commit/27ac805141c0d170a40c2a7796a589a5ef29544f))


### Bug Fixes

* parsing ai recommendations from gemini ([#909](https://github.com/ellite/Wallos/issues/909)) ([27ac805](https://github.com/ellite/Wallos/commit/27ac805141c0d170a40c2a7796a589a5ef29544f))

## [4.1.1](https://github.com/ellite/Wallos/compare/v4.1.0...v4.1.1) (2025-08-13)


### Bug Fixes

* missing apikey validation error on get_monthly_cost api endpoint ([3ecc160](https://github.com/ellite/Wallos/commit/3ecc160ccb73f22367bea427315519876de74a65))
* redirect from dashboard to subscriptions for new users ([3ecc160](https://github.com/ellite/Wallos/commit/3ecc160ccb73f22367bea427315519876de74a65))
* wrong check for disabling password login ([3ecc160](https://github.com/ellite/Wallos/commit/3ecc160ccb73f22367bea427315519876de74a65))

## [4.1.0](https://github.com/ellite/Wallos/compare/v4.0.0...v4.1.0) (2025-08-11)


### Features

* add at a glance dashboard ([ba6dddf](https://github.com/ellite/Wallos/commit/ba6dddf52601fdbeb18897731beacc48d16043c3))
* add get_oidc_settings endpoint to the api ([ba6dddf](https://github.com/ellite/Wallos/commit/ba6dddf52601fdbeb18897731beacc48d16043c3))
* ai recommendations with chatgpt, gemini or ollama ([ba6dddf](https://github.com/ellite/Wallos/commit/ba6dddf52601fdbeb18897731beacc48d16043c3))
* allow to disable password login when oidc is enabled ([ba6dddf](https://github.com/ellite/Wallos/commit/ba6dddf52601fdbeb18897731beacc48d16043c3))
* display ai recommendations on the dashboard ([ba6dddf](https://github.com/ellite/Wallos/commit/ba6dddf52601fdbeb18897731beacc48d16043c3))
* refactor css colors ([ba6dddf](https://github.com/ellite/Wallos/commit/ba6dddf52601fdbeb18897731beacc48d16043c3))


### Bug Fixes

* accept both api_key and apiKey as parameter on the api ([ba6dddf](https://github.com/ellite/Wallos/commit/ba6dddf52601fdbeb18897731beacc48d16043c3))

## [4.0.0](https://github.com/ellite/Wallos/compare/v3.3.1...v4.0.0) (2025-07-21)


### ⚠ BREAKING CHANGES

* add oauth / oidc support ([#875](https://github.com/ellite/Wallos/issues/875))

### Features

* add oauth / oidc support ([#875](https://github.com/ellite/Wallos/issues/875)) ([805e688](https://github.com/ellite/Wallos/commit/805e688ec0fac1dbb362e847ed8a4e3e301ee113))
* add oauth/oidc support ([#873](https://github.com/ellite/Wallos/issues/873)) ([c0d53e4](https://github.com/ellite/Wallos/commit/c0d53e4423996595e5c82404af92e077c00eae47))

## [3.3.1](https://github.com/ellite/Wallos/compare/v3.3.0...v3.3.1) (2025-07-19)


### Bug Fixes

* code of new taiwan dollar ([596cbc4](https://github.com/ellite/Wallos/commit/596cbc42464100dc8c6db5d07c090dab4b767268))
* decoding of header from database on the webhook notifications ([596cbc4](https://github.com/ellite/Wallos/commit/596cbc42464100dc8c6db5d07c090dab4b767268))
* unicode issue on telegram notifications ([#871](https://github.com/ellite/Wallos/issues/871)) ([596cbc4](https://github.com/ellite/Wallos/commit/596cbc42464100dc8c6db5d07c090dab4b767268))

## [3.3.0](https://github.com/ellite/Wallos/compare/v3.2.0...v3.3.0) (2025-06-09)


### Features

* set todays date on start subscription field for new subscriptions by default ([#848](https://github.com/ellite/Wallos/issues/848)) ([d3fd938](https://github.com/ellite/Wallos/commit/d3fd9387d34f430adb84ef553193b4ad3080c009))


### Bug Fixes

* visual issue with date fields on ios ([#846](https://github.com/ellite/Wallos/issues/846)) ([e2df8f7](https://github.com/ellite/Wallos/commit/e2df8f7e24678f9d62f36f68c94de838fc741913))

## [3.2.0](https://github.com/ellite/Wallos/compare/v3.1.1...v3.2.0) (2025-06-08)


### Features

* add button to auto fill the next payment date ([48db4e3](https://github.com/ellite/Wallos/commit/48db4e300df6128b7cc0b4e0c86271bfb3159545))
* add first and last names to the user profile ([48db4e3](https://github.com/ellite/Wallos/commit/48db4e300df6128b7cc0b4e0c86271bfb3159545))
* add indonesian language ([#842](https://github.com/ellite/Wallos/issues/842)) ([48db4e3](https://github.com/ellite/Wallos/commit/48db4e300df6128b7cc0b4e0c86271bfb3159545))
* add new currency ([48db4e3](https://github.com/ellite/Wallos/commit/48db4e300df6128b7cc0b4e0c86271bfb3159545))
* Add new currency ([#829](https://github.com/ellite/Wallos/issues/829)) ([288ad45](https://github.com/ellite/Wallos/commit/288ad456564c307018541a09df447898e1d62d26))
* enable IPv6 environments by configuring a dual-stack listen in nginx ([48db4e3](https://github.com/ellite/Wallos/commit/48db4e300df6128b7cc0b4e0c86271bfb3159545))


### Bug Fixes

* vulnerability on test webhook endpoint ([48db4e3](https://github.com/ellite/Wallos/commit/48db4e300df6128b7cc0b4e0c86271bfb3159545))

## [3.1.1](https://github.com/ellite/Wallos/compare/v3.1.0...v3.1.1) (2025-05-15)


### Bug Fixes

* issue listing prices when uah  was added to the list of currencies ([#823](https://github.com/ellite/Wallos/issues/823)) ([bd20b56](https://github.com/ellite/Wallos/commit/bd20b5697659fc6117113205a3995d7e5f9026c9))

## [3.1.0](https://github.com/ellite/Wallos/compare/v3.0.2...v3.1.0) (2025-05-08)


### Features

* add danish translation ([0cfefc7](https://github.com/ellite/Wallos/commit/0cfefc7f07056d59ad911f926cc56ff3e6c8e261))


### Bug Fixes

* disable totp with backup code ([0cfefc7](https://github.com/ellite/Wallos/commit/0cfefc7f07056d59ad911f926cc56ff3e6c8e261))
* gotify settings test ([0cfefc7](https://github.com/ellite/Wallos/commit/0cfefc7f07056d59ad911f926cc56ff3e6c8e261))
* vulnerability adding logos from url ([0cfefc7](https://github.com/ellite/Wallos/commit/0cfefc7f07056d59ad911f926cc56ff3e6c8e261))

## [3.0.2](https://github.com/ellite/Wallos/compare/v3.0.1...v3.0.2) (2025-05-03)


### Bug Fixes

* delete avatar would not work if wallos is on a subfolder ([69c7d52](https://github.com/ellite/Wallos/commit/69c7d52cf8d708bcb046343faa663209c8d36779))
* some strings not using translations on the calendar page ([69c7d52](https://github.com/ellite/Wallos/commit/69c7d52cf8d708bcb046343faa663209c8d36779))
* vulnerability on delete avatar ([69c7d52](https://github.com/ellite/Wallos/commit/69c7d52cf8d708bcb046343faa663209c8d36779))

## [3.0.1](https://github.com/ellite/Wallos/compare/v3.0.0...v3.0.1) (2025-04-30)


### Bug Fixes

* allow to clear the budget field ([f6b8fb9](https://github.com/ellite/Wallos/commit/f6b8fb9162c5fb4fefa1fbd9cde65c201e96be6c))
* don't show budget alert when budget is 0 ([f6b8fb9](https://github.com/ellite/Wallos/commit/f6b8fb9162c5fb4fefa1fbd9cde65c201e96be6c))

## [3.0.0](https://github.com/ellite/Wallos/compare/v2.52.2...v3.0.0) (2025-04-27)


### ⚠ BREAKING CHANGES

* simplified webhook notifications without iterator (might break your current webhook settings)

### Features

* simplified webhook notifications without iterator (might break your current webhook settings) ([e0f2048](https://github.com/ellite/Wallos/commit/e0f204803e635400c404529d87e5057c579c8531))
* use mobile style toggles instead of checkboxes ([e0f2048](https://github.com/ellite/Wallos/commit/e0f204803e635400c404529d87e5057c579c8531))
* webhooks can now be used for cancelation notifications ([e0f2048](https://github.com/ellite/Wallos/commit/e0f204803e635400c404529d87e5057c579c8531))


### Bug Fixes

* barely readable placeholder text on textarea on dark the ([e0f2048](https://github.com/ellite/Wallos/commit/e0f204803e635400c404529d87e5057c579c8531))

## [2.52.2](https://github.com/ellite/Wallos/compare/v2.52.1...v2.52.2) (2025-04-26)


### Bug Fixes

* incorrect headers on the api ([#802](https://github.com/ellite/Wallos/issues/802)) ([af68c11](https://github.com/ellite/Wallos/commit/af68c11abf5d5a64fd7136e1d2e37323d170c77e))

## [2.52.1](https://github.com/ellite/Wallos/compare/v2.52.0...v2.52.1) (2025-04-26)


### Bug Fixes

* error on statistics page when budget = 0 ([#800](https://github.com/ellite/Wallos/issues/800)) ([b7712dc](https://github.com/ellite/Wallos/commit/b7712dc80d6642a6a33a28adc641f9a4b3263ae6))

## [2.52.0](https://github.com/ellite/Wallos/compare/v2.51.1...v2.52.0) (2025-04-19)


### Features

* new graph cost vs budget on statistics ([#793](https://github.com/ellite/Wallos/issues/793)) ([6d67319](https://github.com/ellite/Wallos/commit/6d673195ba39f1a52e9ea16bad21221768690e7a))

## [2.51.1](https://github.com/ellite/Wallos/compare/v2.51.0...v2.51.1) (2025-04-19)


### Bug Fixes

* timezone for cronjobs now comes from TZ env var first ([#791](https://github.com/ellite/Wallos/issues/791)) ([66a1a45](https://github.com/ellite/Wallos/commit/66a1a45f2dc1df99f8292cbb531d569f706eca6d))

## [2.51.0](https://github.com/ellite/Wallos/compare/v2.50.1...v2.51.0) (2025-04-18)


### Features

* add over budget warnings on the calendar ([88eae10](https://github.com/ellite/Wallos/commit/88eae1002f0cc29a847e95b7698ab713779ec4f4))


### Bug Fixes

* force correct timezone on the cronjobs ([88eae10](https://github.com/ellite/Wallos/commit/88eae1002f0cc29a847e95b7698ab713779ec4f4))

## [2.50.1](https://github.com/ellite/Wallos/compare/v2.50.0...v2.50.1) (2025-04-16)


### Bug Fixes

* localization on date on browsers not in english ([c7b3fb4](https://github.com/ellite/Wallos/commit/c7b3fb445182e19bc464ac987977bac266628757))

## [2.50.0](https://github.com/ellite/Wallos/compare/v2.49.1...v2.50.0) (2025-04-16)


### Features

* shorten date displayed on the list of subscriptions ([68f1d47](https://github.com/ellite/Wallos/commit/68f1d4757737de50622bb4b2aeb8f291dec62972))
* use user defined language for the date on the list of subscriptions ([68f1d47](https://github.com/ellite/Wallos/commit/68f1d4757737de50622bb4b2aeb8f291dec62972))


### Bug Fixes

* limit name display, when sub has no logo to two lines ([68f1d47](https://github.com/ellite/Wallos/commit/68f1d4757737de50622bb4b2aeb8f291dec62972))
* use translations on the mobile menu ([68f1d47](https://github.com/ellite/Wallos/commit/68f1d4757737de50622bb4b2aeb8f291dec62972))

## [2.49.1](https://github.com/ellite/Wallos/compare/v2.49.0...v2.49.1) (2025-04-13)


### Bug Fixes

* version number ([eade2d9](https://github.com/ellite/Wallos/commit/eade2d9919e5d30e7be279f53e278fb746095762))

## [2.49.0](https://github.com/ellite/Wallos/compare/v2.48.1...v2.49.0) (2025-04-13)


### Features

* show name on mobile view when subscription has no logo ([9eb2907](https://github.com/ellite/Wallos/commit/9eb2907145297e3b7aac54dd5b51451d961f549a))
* show timezone on sendnotification cronjob on admin page ([9eb2907](https://github.com/ellite/Wallos/commit/9eb2907145297e3b7aac54dd5b51451d961f549a))
* use currencyConverter for notifications as well ([9eb2907](https://github.com/ellite/Wallos/commit/9eb2907145297e3b7aac54dd5b51451d961f549a))
* use symbol from db when currencyFormatter does not support the currency ([9eb2907](https://github.com/ellite/Wallos/commit/9eb2907145297e3b7aac54dd5b51451d961f549a))


### Bug Fixes

* date comparison check on sendnotifications cronjob ([9eb2907](https://github.com/ellite/Wallos/commit/9eb2907145297e3b7aac54dd5b51451d961f549a))
* emails with encryption set to none not working without ssl ([9eb2907](https://github.com/ellite/Wallos/commit/9eb2907145297e3b7aac54dd5b51451d961f549a))
* error when not setting custom headers for ntfy ([9eb2907](https://github.com/ellite/Wallos/commit/9eb2907145297e3b7aac54dd5b51451d961f549a))

## [2.48.1](https://github.com/ellite/Wallos/compare/v2.48.0...v2.48.1) (2025-03-27)


### Bug Fixes

* notifications would also be sent x days after subscription was due in some cases ([ba912a3](https://github.com/ellite/Wallos/commit/ba912a37d1a0d95401a38dabe8f98f29a6aa49db))

## [2.48.0](https://github.com/ellite/Wallos/compare/v2.47.1...v2.48.0) (2025-03-20)


### Features

* add update notification and release notes to the about page ([3e0e88d](https://github.com/ellite/Wallos/commit/3e0e88d6a2adc46c17773b09dd8684618c979711))
* increase privacy by not sending referrer to external urls ([3e0e88d](https://github.com/ellite/Wallos/commit/3e0e88d6a2adc46c17773b09dd8684618c979711))
* small layout change on the about page ([3e0e88d](https://github.com/ellite/Wallos/commit/3e0e88d6a2adc46c17773b09dd8684618c979711))

## [2.47.1](https://github.com/ellite/Wallos/compare/v2.47.0...v2.47.1) (2025-03-19)


### Bug Fixes

* small layout inconsistencies on the dashboard ([19d3067](https://github.com/ellite/Wallos/commit/19d30672b2635b6e79eaa6eb5c49100d7a27a63a))

## [2.47.0](https://github.com/ellite/Wallos/compare/v2.46.1...v2.47.0) (2025-03-19)


### Features

* add filter by renew type ([1bec973](https://github.com/ellite/Wallos/commit/1bec973803e0b3c00d2765bbf80447439127574d))
* add sort by renew type ([1bec973](https://github.com/ellite/Wallos/commit/1bec973803e0b3c00d2765bbf80447439127574d))
* add ukranian translation ([#756](https://github.com/ellite/Wallos/issues/756)) ([1bec973](https://github.com/ellite/Wallos/commit/1bec973803e0b3c00d2765bbf80447439127574d))
* remove "Wallos" text from calendar export ([1bec973](https://github.com/ellite/Wallos/commit/1bec973803e0b3c00d2765bbf80447439127574d))


### Bug Fixes

* ical trigger to spec RFC5545 ([1bec973](https://github.com/ellite/Wallos/commit/1bec973803e0b3c00d2765bbf80447439127574d))
* special chars on calendar exports ([1bec973](https://github.com/ellite/Wallos/commit/1bec973803e0b3c00d2765bbf80447439127574d))
* special chars on notifications ([1bec973](https://github.com/ellite/Wallos/commit/1bec973803e0b3c00d2765bbf80447439127574d))
* state filter not cleared by clear button ([1bec973](https://github.com/ellite/Wallos/commit/1bec973803e0b3c00d2765bbf80447439127574d))

## [2.46.1](https://github.com/ellite/Wallos/compare/v2.46.0...v2.46.1) (2025-03-06)


### Bug Fixes

* calculation of monthly cost progress graph ([#747](https://github.com/ellite/Wallos/issues/747)) ([77486ec](https://github.com/ellite/Wallos/commit/77486ec92c44b71f69e85b1eafb7f3a98c4a44c1))

## [2.46.0](https://github.com/ellite/Wallos/compare/v2.45.2...v2.46.0) (2025-02-22)


### Features

* sorting by category or payment method respects order from the settings page ([51b2272](https://github.com/ellite/Wallos/commit/51b22727bf5656a4a263519b5b56adfe6a2d12be))


### Bug Fixes

* access to tmp folder by www-data ([51b2272](https://github.com/ellite/Wallos/commit/51b22727bf5656a4a263519b5b56adfe6a2d12be))

## [2.45.2](https://github.com/ellite/Wallos/compare/v2.45.1...v2.45.2) (2025-02-05)


### Bug Fixes

* bug setting main currency for the first registered user ([c43b08a](https://github.com/ellite/Wallos/commit/c43b08aa4c45c907f82eb6afe37fd46aa5103654))
* deprecation message ([c43b08a](https://github.com/ellite/Wallos/commit/c43b08aa4c45c907f82eb6afe37fd46aa5103654))
* subscription progress above 100% for disabled subscriptions ([c43b08a](https://github.com/ellite/Wallos/commit/c43b08aa4c45c907f82eb6afe37fd46aa5103654))
* typo on czech translation ([c43b08a](https://github.com/ellite/Wallos/commit/c43b08aa4c45c907f82eb6afe37fd46aa5103654))
* use first currency on the list of currencies if user has not selected a main currency ([c43b08a](https://github.com/ellite/Wallos/commit/c43b08aa4c45c907f82eb6afe37fd46aa5103654))
* use gd if imagick is not available ([c43b08a](https://github.com/ellite/Wallos/commit/c43b08aa4c45c907f82eb6afe37fd46aa5103654))

## [2.45.1](https://github.com/ellite/Wallos/compare/v2.45.0...v2.45.1) (2025-01-28)


### Bug Fixes

* improve czech translation ([e2dc269](https://github.com/ellite/Wallos/commit/e2dc2696310159900c1f8fbe0a090e66b29b778d))
* improve japanese translation ([#713](https://github.com/ellite/Wallos/issues/713)) ([e2dc269](https://github.com/ellite/Wallos/commit/e2dc2696310159900c1f8fbe0a090e66b29b778d))
* improve traditional chinese translation ([e2dc269](https://github.com/ellite/Wallos/commit/e2dc2696310159900c1f8fbe0a090e66b29b778d))
* setting pgid and puid for the container ([e2dc269](https://github.com/ellite/Wallos/commit/e2dc2696310159900c1f8fbe0a090e66b29b778d))

## [2.45.0](https://github.com/ellite/Wallos/compare/v2.44.1...v2.45.0) (2025-01-19)


### Features

* add czech translations ([#701](https://github.com/ellite/Wallos/issues/701)) ([426fdfa](https://github.com/ellite/Wallos/commit/426fdfa5c79d32c7d5a0722a0590d39547cfd1fa))

## [2.44.1](https://github.com/ellite/Wallos/compare/v2.44.0...v2.44.1) (2025-01-19)


### Bug Fixes

* error setting date of last exchange rates update ([#699](https://github.com/ellite/Wallos/issues/699)) ([d2f68c4](https://github.com/ellite/Wallos/commit/d2f68c457e9b1328caf983ddc6e2827430855aa6))

## [2.44.0](https://github.com/ellite/Wallos/compare/v2.43.1...v2.44.0) (2025-01-12)


### Features

* allow notifications on due date ([87f148d](https://github.com/ellite/Wallos/commit/87f148d1745bec19f5713b8a367a3615871e6e33))


### Bug Fixes

* don't expose disabled notifications to ical feed ([87f148d](https://github.com/ellite/Wallos/commit/87f148d1745bec19f5713b8a367a3615871e6e33))
* email notification test always sending to admins email ([87f148d](https://github.com/ellite/Wallos/commit/87f148d1745bec19f5713b8a367a3615871e6e33))

## [2.43.1](https://github.com/ellite/Wallos/compare/v2.43.0...v2.43.1) (2025-01-12)


### Bug Fixes

* edit / delete subscription menu not accessible ([#689](https://github.com/ellite/Wallos/issues/689)) ([b668d37](https://github.com/ellite/Wallos/commit/b668d37d38f799ee0dda5a69a4824d03dd21e1bc))

## [2.43.0](https://github.com/ellite/Wallos/compare/v2.42.2...v2.43.0) (2025-01-11)


### Features

* new api endpoint that returns the version ([ff13fcb](https://github.com/ellite/Wallos/commit/ff13fcb6547ec4a9c972a2c0f0b6f42d69620f8b))
* option to show progress of subscription cycle ([ff13fcb](https://github.com/ellite/Wallos/commit/ff13fcb6547ec4a9c972a2c0f0b6f42d69620f8b))


### Bug Fixes

* currency symbol for monthly budget ([ff13fcb](https://github.com/ellite/Wallos/commit/ff13fcb6547ec4a9c972a2c0f0b6f42d69620f8b))

## [2.42.2](https://github.com/ellite/Wallos/compare/v2.42.1...v2.42.2) (2024-12-21)


### Bug Fixes

* version number ([#668](https://github.com/ellite/Wallos/issues/668)) ([683a366](https://github.com/ellite/Wallos/commit/683a3662ff998066f5d8de3be88e4d40d766442a))

## [2.42.1](https://github.com/ellite/Wallos/compare/v2.42.0...v2.42.1) (2024-12-21)


### Bug Fixes

* remove debug echo on stats page ([#666](https://github.com/ellite/Wallos/issues/666)) ([d9a2488](https://github.com/ellite/Wallos/commit/d9a24885ffbbdb3c08d9015804eea8cb0fea6cea))

## [2.42.0](https://github.com/ellite/Wallos/compare/v2.41.0...v2.42.0) (2024-12-21)


### Features

* add total monthly cost trend graph to the statistics page ([e7185f9](https://github.com/ellite/Wallos/commit/e7185f92578b3103d097b12b8c4313635f263d9f))
* allow email notifications without authentication ([e7185f9](https://github.com/ellite/Wallos/commit/e7185f92578b3103d097b12b8c4313635f263d9f))


### Bug Fixes

* don't update next payment date for disabled subscriptions ([e7185f9](https://github.com/ellite/Wallos/commit/e7185f92578b3103d097b12b8c4313635f263d9f))
* xss security vulnerability with the avatar selection ([e7185f9](https://github.com/ellite/Wallos/commit/e7185f92578b3103d097b12b8c4313635f263d9f))

## [2.41.0](https://github.com/ellite/Wallos/compare/v2.40.0...v2.41.0) (2024-12-11)


### Features

* add payment cycle to csv/json export ([5e6bc90](https://github.com/ellite/Wallos/commit/5e6bc903bcd95580ed58f744977d92c6330b3d9f))
* run db migration after importing db ([5e6bc90](https://github.com/ellite/Wallos/commit/5e6bc903bcd95580ed58f744977d92c6330b3d9f))
* run db migration after restoring database ([5e6bc90](https://github.com/ellite/Wallos/commit/5e6bc903bcd95580ed58f744977d92c6330b3d9f))
* store weekly the total yearly cost of subscriptions ([5e6bc90](https://github.com/ellite/Wallos/commit/5e6bc903bcd95580ed58f744977d92c6330b3d9f))


### Bug Fixes

* double encoding in statistics labels ([5e6bc90](https://github.com/ellite/Wallos/commit/5e6bc903bcd95580ed58f744977d92c6330b3d9f))

## [2.40.0](https://github.com/ellite/Wallos/compare/v2.39.1...v2.40.0) (2024-12-10)


### Features

* add dutch translation ([#655](https://github.com/ellite/Wallos/issues/655)) ([b5a9880](https://github.com/ellite/Wallos/commit/b5a98806d1f453180ce15724fa198d248177e488))

## [2.39.1](https://github.com/ellite/Wallos/compare/v2.39.0...v2.39.1) (2024-12-06)


### Bug Fixes

* svg error on calendar page ([#650](https://github.com/ellite/Wallos/issues/650)) ([8ba79c0](https://github.com/ellite/Wallos/commit/8ba79c0725815c6de8458c74961bbdf23a7d3e9d))

## [2.39.0](https://github.com/ellite/Wallos/compare/v2.38.3...v2.39.0) (2024-12-06)


### Features

* add icalendar subscription ([f5ddbff](https://github.com/ellite/Wallos/commit/f5ddbff0c1e0be676604390101c56c04c778f56a))

## [2.38.3](https://github.com/ellite/Wallos/compare/v2.38.2...v2.38.3) (2024-12-06)


### Bug Fixes

* vulnerability on the restore database endpoints ([3b2de8b](https://github.com/ellite/Wallos/commit/3b2de8b7c22090afdf7115c25fd8b497a5626ea3))

## [2.38.2](https://github.com/ellite/Wallos/compare/v2.38.1...v2.38.2) (2024-11-19)


### Bug Fixes

* logo search positioned below other elements ([#637](https://github.com/ellite/Wallos/issues/637)) ([72f7e57](https://github.com/ellite/Wallos/commit/72f7e5791423c45f910a791b20aafba301d0172f))

## [2.38.1](https://github.com/ellite/Wallos/compare/v2.38.0...v2.38.1) (2024-11-17)


### Bug Fixes

* bug introduced on 2.38.0 on the subscriptions dashboard ([#634](https://github.com/ellite/Wallos/issues/634)) ([f63c543](https://github.com/ellite/Wallos/commit/f63c543cdd7512b216004db3b279884dbda87ce4))

## [2.38.0](https://github.com/ellite/Wallos/compare/v2.37.1...v2.38.0) (2024-11-17)


### Features

* add option for manual/automatic renewals ([6e44a26](https://github.com/ellite/Wallos/commit/6e44a26703486d0ba30ee6ae8d3c46bfc3c6630a))
* add some leeway for totp codes ([6e44a26](https://github.com/ellite/Wallos/commit/6e44a26703486d0ba30ee6ae8d3c46bfc3c6630a))
* add start date to subscriptions ([6e44a26](https://github.com/ellite/Wallos/commit/6e44a26703486d0ba30ee6ae8d3c46bfc3c6630a))


### Bug Fixes

* layout issue with subscriptions list during search ([6e44a26](https://github.com/ellite/Wallos/commit/6e44a26703486d0ba30ee6ae8d3c46bfc3c6630a))

## [2.37.1](https://github.com/ellite/Wallos/compare/v2.37.0...v2.37.1) (2024-11-15)


### Bug Fixes

* version mismatch ([#627](https://github.com/ellite/Wallos/issues/627)) ([c4a9b16](https://github.com/ellite/Wallos/commit/c4a9b1627fbc7278398bf2d8bf7cae2934d349ca))

## [2.37.0](https://github.com/ellite/Wallos/compare/v2.36.2...v2.37.0) (2024-11-15)


### Features

* add monthly statistics to the calendar page ([f085f8a](https://github.com/ellite/Wallos/commit/f085f8adece3af2548858f665db16d4843d3e622))


### Bug Fixes

* notifications being sent on the wrong day ([f085f8a](https://github.com/ellite/Wallos/commit/f085f8adece3af2548858f665db16d4843d3e622))

## [2.36.2](https://github.com/ellite/Wallos/compare/v2.36.1...v2.36.2) (2024-11-03)


### Bug Fixes

* only show swipe hint on mobile screens ([#612](https://github.com/ellite/Wallos/issues/612)) ([bd5e351](https://github.com/ellite/Wallos/commit/bd5e3511829a798ab47ca5e9c9d080aae45ae1a0))

## [2.36.1](https://github.com/ellite/Wallos/compare/v2.36.0...v2.36.1) (2024-11-03)


### Bug Fixes

* version number ([#610](https://github.com/ellite/Wallos/issues/610)) ([4bd40f1](https://github.com/ellite/Wallos/commit/4bd40f1c561e979322375b95aeccccd18c4780fd))

## [2.36.0](https://github.com/ellite/Wallos/compare/v2.35.0...v2.36.0) (2024-11-03)


### Features

* add hint for mobile swipe action ([#608](https://github.com/ellite/Wallos/issues/608)) ([49666f8](https://github.com/ellite/Wallos/commit/49666f867cdbaa4d4c0c1551d0b4b3023830606a))

## [2.35.0](https://github.com/ellite/Wallos/compare/v2.34.0...v2.35.0) (2024-11-01)


### Features

* new menu icons ([28444ab](https://github.com/ellite/Wallos/commit/28444abef1cee338e41e57cbf6f13666b917bbde))
* swipe subscription for actions on the experimental mobile navigation ([28444ab](https://github.com/ellite/Wallos/commit/28444abef1cee338e41e57cbf6f13666b917bbde))

## [2.34.0](https://github.com/ellite/Wallos/compare/v2.33.1...v2.34.0) (2024-10-31)


### Features

* link version update banner to github release ([f007adf](https://github.com/ellite/Wallos/commit/f007adf9658eb1fd095c2716e4146130535f6cb7))
* only show filters that are actually used ([f007adf](https://github.com/ellite/Wallos/commit/f007adf9658eb1fd095c2716e4146130535f6cb7))


### Bug Fixes

* filters for categories and payment method respect order from settings ([f007adf](https://github.com/ellite/Wallos/commit/f007adf9658eb1fd095c2716e4146130535f6cb7))

## [2.33.1](https://github.com/ellite/Wallos/compare/v2.33.0...v2.33.1) (2024-10-30)


### Bug Fixes

* improve localization ([6480f87](https://github.com/ellite/Wallos/commit/6480f8744094d5ce0f05d7d155925540ac73b156))
* layout issue on the settings page ([#598](https://github.com/ellite/Wallos/issues/598)) ([6480f87](https://github.com/ellite/Wallos/commit/6480f8744094d5ce0f05d7d155925540ac73b156))

## [2.33.0](https://github.com/ellite/Wallos/compare/v2.32.0...v2.33.0) (2024-10-29)


### Features

* replacement for disabled subscriptions, to more accurately calculate savings ([5c92528](https://github.com/ellite/Wallos/commit/5c9252880837a7886c903ddc7ae92c8fed29b452))

## [2.32.0](https://github.com/ellite/Wallos/compare/v2.31.1...v2.32.0) (2024-10-27)


### Features

* settings to allow to ignore certificates for some notification methods ([2a0e665](https://github.com/ellite/Wallos/commit/2a0e665e77eca804fa70dafc1a3a0010eb9da270))

## [2.31.1](https://github.com/ellite/Wallos/compare/v2.31.0...v2.31.1) (2024-10-25)


### Bug Fixes

* add missing {{days_until}} variable to string version of the webhook ([ebc7b83](https://github.com/ellite/Wallos/commit/ebc7b83e9a0a32aecf3b1aa933408bf9b6baea3a))
* display actual error message when email test fails ([ebc7b83](https://github.com/ellite/Wallos/commit/ebc7b83e9a0a32aecf3b1aa933408bf9b6baea3a))

## [2.31.0](https://github.com/ellite/Wallos/compare/v2.30.1...v2.31.0) (2024-10-22)


### Features

* handle webhook payload as string if it is not a json object ([#583](https://github.com/ellite/Wallos/issues/583)) ([ee834d6](https://github.com/ellite/Wallos/commit/ee834d6198fa3315facd23a734655adf391bb736))

## [2.30.1](https://github.com/ellite/Wallos/compare/v2.30.0...v2.30.1) (2024-10-14)


### Bug Fixes

* verify correct path before creating logos folder ([782ebcd](https://github.com/ellite/Wallos/commit/782ebcd64fc947ea82eabaac6bc26a32676271a1))

## [2.30.0](https://github.com/ellite/Wallos/compare/v2.29.2...v2.30.0) (2024-10-13)


### Features

* add vietnamese translation ([#573](https://github.com/ellite/Wallos/issues/573)) ([45ff10f](https://github.com/ellite/Wallos/commit/45ff10f953f4af681252ed4d77c32b375f9c396c))

## [2.29.2](https://github.com/ellite/Wallos/compare/v2.29.1...v2.29.2) (2024-10-11)


### Bug Fixes

* xss issue on the dashboard ([#568](https://github.com/ellite/Wallos/issues/568)) ([e642129](https://github.com/ellite/Wallos/commit/e6421296aa708b02c468b10e3c9d0f28012c1282))

## [2.29.1](https://github.com/ellite/Wallos/compare/v2.29.0...v2.29.1) (2024-10-11)


### Bug Fixes

* mysql injection vulnerability ([3d6a8c3](https://github.com/ellite/Wallos/commit/3d6a8c340843230eff97b459e85efbea55aac01f))
* new profile page not being cached by service worker ([3d6a8c3](https://github.com/ellite/Wallos/commit/3d6a8c340843230eff97b459e85efbea55aac01f))

## [2.29.0](https://github.com/ellite/Wallos/compare/v2.28.0...v2.29.0) (2024-10-09)


### Features

* add url and notes as variables for the notifications webhook ([790defb](https://github.com/ellite/Wallos/commit/790defb2b1d1cd3d8c93738155edb19f96d0aa2a))


### Bug Fixes

* bug when looping multiple subscriptions on the notifications webhook ([790defb](https://github.com/ellite/Wallos/commit/790defb2b1d1cd3d8c93738155edb19f96d0aa2a))

## [2.28.0](https://github.com/ellite/Wallos/compare/v2.27.3...v2.28.0) (2024-10-07)


### Features

* get admin setting api endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get categories endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get currencies endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get fixer api endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get household api endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get notifications api endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get payment methods api endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get settings api endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get subscriptions api endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))
* get user api endpoint ([07d456a](https://github.com/ellite/Wallos/commit/07d456a9c3d9cc3eb9ae80edb666caa103cababe))

## [2.27.3](https://github.com/ellite/Wallos/compare/v2.27.2...v2.27.3) (2024-10-05)


### Bug Fixes

* missing folders on baremetal installation ([#554](https://github.com/ellite/Wallos/issues/554)) ([03f34d1](https://github.com/ellite/Wallos/commit/03f34d1aee3f74c3bf9c53c04c1494106be4bb47))
* missing fonts ([03f34d1](https://github.com/ellite/Wallos/commit/03f34d1aee3f74c3bf9c53c04c1494106be4bb47))

## [2.27.2](https://github.com/ellite/Wallos/compare/v2.27.1...v2.27.2) (2024-10-04)


### Bug Fixes

* bump version ([#546](https://github.com/ellite/Wallos/issues/546)) ([c5460bd](https://github.com/ellite/Wallos/commit/c5460bd79bdd056e788774ac52cfd4262eada5e7))

## [2.27.1](https://github.com/ellite/Wallos/compare/v2.27.0...v2.27.1) (2024-10-04)


### Bug Fixes

* add missing assets to the service worker ([#542](https://github.com/ellite/Wallos/issues/542)) ([0251da2](https://github.com/ellite/Wallos/commit/0251da23f4254420a471fcd4c4951d0d0b1bb4df))

## [2.27.0](https://github.com/ellite/Wallos/compare/v2.26.0...v2.27.0) (2024-10-04)


### Features

* api endpoint to calculate monthly cost ([a173d27](https://github.com/ellite/Wallos/commit/a173d2765fd2a1a641f32fbea198775b1bdc0b00))
* fisrt api endpoint ([a173d27](https://github.com/ellite/Wallos/commit/a173d2765fd2a1a641f32fbea198775b1bdc0b00))
* redesigned experimental mobile navigation menu ([a173d27](https://github.com/ellite/Wallos/commit/a173d2765fd2a1a641f32fbea198775b1bdc0b00))
* split settings page into settings and profile page ([a173d27](https://github.com/ellite/Wallos/commit/a173d2765fd2a1a641f32fbea198775b1bdc0b00))
* user has api key available on profile page ([a173d27](https://github.com/ellite/Wallos/commit/a173d2765fd2a1a641f32fbea198775b1bdc0b00))


### Bug Fixes

* small fixes and typos ([a173d27](https://github.com/ellite/Wallos/commit/a173d2765fd2a1a641f32fbea198775b1bdc0b00))

## [2.26.0](https://github.com/ellite/Wallos/compare/v2.25.0...v2.26.0) (2024-09-29)


### Features

* add mobile menu navigation to experimental settings ([1dbba18](https://github.com/ellite/Wallos/commit/1dbba18446ac53568492af9d2aee3f90db7168ca))
* use browsers locale to set dates on the dashboard ([1dbba18](https://github.com/ellite/Wallos/commit/1dbba18446ac53568492af9d2aee3f90db7168ca))

## [2.25.0](https://github.com/ellite/Wallos/compare/v2.24.1...v2.25.0) (2024-09-28)


### Features

* add 2fa support ([#525](https://github.com/ellite/Wallos/issues/525)) ([2f16ab3](https://github.com/ellite/Wallos/commit/2f16ab3fdf89b8ba6b1010510d8b169aad425f38))

## [2.24.1](https://github.com/ellite/Wallos/compare/v2.24.0...v2.24.1) (2024-09-23)


### Bug Fixes

* small layout issue on the settings page ([0623ceb](https://github.com/ellite/Wallos/commit/0623cebe67182b493770615c518977907e11d359))

## [2.24.0](https://github.com/ellite/Wallos/compare/v2.23.2...v2.24.0) (2024-09-18)


### Features

* add button to clean up search field ([da3ee78](https://github.com/ellite/Wallos/commit/da3ee782f13c1eaa98a85de5dbe33714d173a323))


### Bug Fixes

* cases where theme and sort cookies could be missing ([da3ee78](https://github.com/ellite/Wallos/commit/da3ee782f13c1eaa98a85de5dbe33714d173a323))
* position of dropdown on rtl layout ([da3ee78](https://github.com/ellite/Wallos/commit/da3ee782f13c1eaa98a85de5dbe33714d173a323))

## [2.23.2](https://github.com/ellite/Wallos/compare/v2.23.1...v2.23.2) (2024-09-04)


### Bug Fixes

* sort order after edit subscription in case the cookie is missing ([87809fe](https://github.com/ellite/Wallos/commit/87809fea71b92c7518173fedd189d7e76ce11bfb))

## [2.23.1](https://github.com/ellite/Wallos/compare/v2.23.0...v2.23.1) (2024-09-01)


### Bug Fixes

* warning on top of dashboard page ([#512](https://github.com/ellite/Wallos/issues/512)) ([9056722](https://github.com/ellite/Wallos/commit/905672243b75e6b3d367d439bdbbb37d1b5ae0fa))

## [2.23.0](https://github.com/ellite/Wallos/compare/v2.22.1...v2.23.0) (2024-09-01)


### Features

* add multi email recipients ([fed0192](https://github.com/ellite/Wallos/commit/fed0192394e77409dae04d4ab3cdda0ba0c578a4))
* add option for also showing the original price on the dashboard ([fed0192](https://github.com/ellite/Wallos/commit/fed0192394e77409dae04d4ab3cdda0ba0c578a4))
* open edit form after cloning subscription ([fed0192](https://github.com/ellite/Wallos/commit/fed0192394e77409dae04d4ab3cdda0ba0c578a4))
* select multiple filters on the dashboard ([fed0192](https://github.com/ellite/Wallos/commit/fed0192394e77409dae04d4ab3cdda0ba0c578a4))


### Bug Fixes

* export.php csv header typo ([#499](https://github.com/ellite/Wallos/issues/499)) ([6e96c5d](https://github.com/ellite/Wallos/commit/6e96c5d4b0c7264ab37a85e9a8b8062f96f69c5c))
* typo on export subscriptions to csv ([fed0192](https://github.com/ellite/Wallos/commit/fed0192394e77409dae04d4ab3cdda0ba0c578a4))

## [2.22.1](https://github.com/ellite/Wallos/compare/v2.22.0...v2.22.1) (2024-08-11)


### Bug Fixes

* inline items in subscription form out of place ([#489](https://github.com/ellite/Wallos/issues/489)) ([3f33ba0](https://github.com/ellite/Wallos/commit/3f33ba0310af0c903db9bef1dd6668146219142c))

## [2.22.0](https://github.com/ellite/Wallos/compare/v2.21.3...v2.22.0) (2024-08-09)


### Features

* admin can manually trigger cronjobs ([1946ac9](https://github.com/ellite/Wallos/commit/1946ac9855696892b9a0790d46623614aa9aab2c))


### Bug Fixes

* only allow the system and admin to run the cronjobs ([1946ac9](https://github.com/ellite/Wallos/commit/1946ac9855696892b9a0790d46623614aa9aab2c))
* reduce size of the log files of the cronjobs ([1946ac9](https://github.com/ellite/Wallos/commit/1946ac9855696892b9a0790d46623614aa9aab2c))

## [2.21.3](https://github.com/ellite/Wallos/compare/v2.21.2...v2.21.3) (2024-08-08)


### Bug Fixes

* broken avatar upload when using the french language ([cf0d5d3](https://github.com/ellite/Wallos/commit/cf0d5d3df30909a0de7ef84aae2601d805617f90))
* more deprecation warnings on image uploads ([cf0d5d3](https://github.com/ellite/Wallos/commit/cf0d5d3df30909a0de7ef84aae2601d805617f90))

## [2.21.2](https://github.com/ellite/Wallos/compare/v2.21.1...v2.21.2) (2024-08-07)


### Bug Fixes

* add samesite directive to cookies ([8b0325c](https://github.com/ellite/Wallos/commit/8b0325c7d3c672754de220efd52b9ba9de8a9868))
* service worker precaching logout.php causes user to be logged out ([8b0325c](https://github.com/ellite/Wallos/commit/8b0325c7d3c672754de220efd52b9ba9de8a9868))
* sort by price ([8b0325c](https://github.com/ellite/Wallos/commit/8b0325c7d3c672754de220efd52b9ba9de8a9868))

## [2.21.1](https://github.com/ellite/Wallos/compare/v2.21.0...v2.21.1) (2024-08-06)


### Bug Fixes

* deprecation message for null value ([#479](https://github.com/ellite/Wallos/issues/479)) ([0274b1d](https://github.com/ellite/Wallos/commit/0274b1d5257f8f1c4156e2a342df6acf177ad726))

## [2.21.0](https://github.com/ellite/Wallos/compare/v2.20.1...v2.21.0) (2024-08-06)


### Features

* add option to list disabled subscriptions at the bottom ([3281f0c](https://github.com/ellite/Wallos/commit/3281f0ce35fbea237e21221d3a9026ed96ad84e5))
* notification for wallos version updates ([3281f0c](https://github.com/ellite/Wallos/commit/3281f0ce35fbea237e21221d3a9026ed96ad84e5))

## [2.20.1](https://github.com/ellite/Wallos/compare/v2.20.0...v2.20.1) (2024-07-29)


### Bug Fixes

* allow usernames with capital letters ([f241ba2](https://github.com/ellite/Wallos/commit/f241ba23018ee910ab859b2ce860b4c0678d6402))
* use 2 decimal places for price on the calendar ([f241ba2](https://github.com/ellite/Wallos/commit/f241ba23018ee910ab859b2ce860b4c0678d6402))
* use 2 decimal places for price when exporting ical in the calendar ([f241ba2](https://github.com/ellite/Wallos/commit/f241ba23018ee910ab859b2ce860b4c0678d6402))

## [2.20.0](https://github.com/ellite/Wallos/compare/v2.19.3...v2.20.0) (2024-07-19)


### Features

* export subscriptions as csv ([8f1e155](https://github.com/ellite/Wallos/commit/8f1e1554787c6e3ffaf7e73369a66794c0636713))
* export subscriptions as json ([8f1e155](https://github.com/ellite/Wallos/commit/8f1e1554787c6e3ffaf7e73369a66794c0636713))
* user can delete their own account ([8f1e155](https://github.com/ellite/Wallos/commit/8f1e1554787c6e3ffaf7e73369a66794c0636713))

## [2.19.3](https://github.com/ellite/Wallos/compare/v2.19.2...v2.19.3) (2024-07-15)


### Bug Fixes

* delete button on subscription form ([#460](https://github.com/ellite/Wallos/issues/460)) ([8cb4355](https://github.com/ellite/Wallos/commit/8cb43553fd2d3328fe9b1f7c5986e040071844c0))

## [2.19.2](https://github.com/ellite/Wallos/compare/v2.19.1...v2.19.2) (2024-07-15)


### Bug Fixes

* test ntfy without custom headers ([#456](https://github.com/ellite/Wallos/issues/456)) ([8fcfc92](https://github.com/ellite/Wallos/commit/8fcfc9264726ec1ded81ca2c51daa65ae9f4e7d8))

## [2.19.1](https://github.com/ellite/Wallos/compare/v2.19.0...v2.19.1) (2024-07-14)


### Bug Fixes

* unset sortOrder var ([a1fab4d](https://github.com/ellite/Wallos/commit/a1fab4dd1067f80054a2c52710edb859dba47127))

## [2.19.0](https://github.com/ellite/Wallos/compare/v2.18.0...v2.19.0) (2024-07-14)


### Features

* add alphanumeric sort order for subscriptions ([#449](https://github.com/ellite/Wallos/issues/449)) ([775e6ee](https://github.com/ellite/Wallos/commit/775e6ee39457edef420d5c36fb310a75fd47bff6))

## [2.18.0](https://github.com/ellite/Wallos/compare/v2.17.0...v2.18.0) (2024-07-14)


### Features

* disable display options checkbox when fixer key is not set ([5f10525](https://github.com/ellite/Wallos/commit/5f1052584b5ece93ebdcb5bce32210e2643a9f26))
* display error message on the statistics page when the fixer key is needed but is missing ([5f10525](https://github.com/ellite/Wallos/commit/5f1052584b5ece93ebdcb5bce32210e2643a9f26))

## [2.17.0](https://github.com/ellite/Wallos/compare/v2.16.1...v2.17.0) (2024-07-11)


### Features

* add filter and sort dashboard by subscription state ([afff992](https://github.com/ellite/Wallos/commit/afff992878287fdc51229297c455d1f69216c36e))


### Bug Fixes

* use the same font for inputs ([a539058](https://github.com/ellite/Wallos/commit/a5390580259105f14154b0d7ce1eb13631c471b1))

## [2.16.1](https://github.com/ellite/Wallos/compare/v2.16.0...v2.16.1) (2024-07-10)


### Bug Fixes

* error when logos folder is empty ([#439](https://github.com/ellite/Wallos/issues/439)) ([e2e5061](https://github.com/ellite/Wallos/commit/e2e5061d1506652384ceed018aa4330b8548b792))

## [2.16.0](https://github.com/ellite/Wallos/compare/v2.15.0...v2.16.0) (2024-07-10)


### Features

* add calendar to pwa shortcuts ([21ebf29](https://github.com/ellite/Wallos/commit/21ebf29f11405ab24b1b0ffd16eb667de4dfc189))
* change apple touch icon ([21ebf29](https://github.com/ellite/Wallos/commit/21ebf29f11405ab24b1b0ffd16eb667de4dfc189))

## [2.15.0](https://github.com/ellite/Wallos/compare/v2.14.2...v2.15.0) (2024-07-09)


### Features

* add maintenance tasks to admin page ([9f7f47b](https://github.com/ellite/Wallos/commit/9f7f47b5d1be2697c2c612bfddb6119c63a3d517))
* add support to upload svg logos ([9f7f47b](https://github.com/ellite/Wallos/commit/9f7f47b5d1be2697c2c612bfddb6119c63a3d517))

## [2.14.2](https://github.com/ellite/Wallos/compare/v2.14.1...v2.14.2) (2024-07-08)


### Bug Fixes

* broken subscription update query ([#431](https://github.com/ellite/Wallos/issues/431)) ([b00a985](https://github.com/ellite/Wallos/commit/b00a9855453663aeb2f1f4b7f0db3aca3994b12b))

## [2.14.1](https://github.com/ellite/Wallos/compare/v2.14.0...v2.14.1) (2024-07-05)


### Bug Fixes

* dashboard scrolling to top when opening a subscription ([#427](https://github.com/ellite/Wallos/issues/427)) ([cb03af8](https://github.com/ellite/Wallos/commit/cb03af8e46fb5ec5138ed7ef729f4b56a23d2b37))

## [2.14.0](https://github.com/ellite/Wallos/compare/v2.13.0...v2.14.0) (2024-07-05)


### Features

* add cancelation reminders ([#425](https://github.com/ellite/Wallos/issues/425)) ([c393146](https://github.com/ellite/Wallos/commit/c393146d9e3d494943de32ecd86983335358cf88))

## [2.13.0](https://github.com/ellite/Wallos/compare/v2.12.0...v2.13.0) (2024-07-04)


### Features

* uniformize layout and styles (+ checkboxes and radios) ([#423](https://github.com/ellite/Wallos/issues/423)) ([c166c7e](https://github.com/ellite/Wallos/commit/c166c7e84c06ceba5ab21341c8d56bd1aaf042ec))

## [2.12.0](https://github.com/ellite/Wallos/compare/v2.11.2...v2.12.0) (2024-07-03)


### Features

* ability to add custom css styles ([50bd104](https://github.com/ellite/Wallos/commit/50bd104b5b990605f457b540bec95eff5034473d))
* cache logos for offline use ([50bd104](https://github.com/ellite/Wallos/commit/50bd104b5b990605f457b540bec95eff5034473d))
* more uniform and aligned styles on the settings page ([50bd104](https://github.com/ellite/Wallos/commit/50bd104b5b990605f457b540bec95eff5034473d))
* rework styles of theme section on settings page ([50bd104](https://github.com/ellite/Wallos/commit/50bd104b5b990605f457b540bec95eff5034473d))


### Bug Fixes

* don't allow saving main and accent colors if they're the same ([50bd104](https://github.com/ellite/Wallos/commit/50bd104b5b990605f457b540bec95eff5034473d))

## [2.11.2](https://github.com/ellite/Wallos/compare/v2.11.1...v2.11.2) (2024-07-02)


### Bug Fixes

* menus checkmark position ([#419](https://github.com/ellite/Wallos/issues/419)) ([4da5d47](https://github.com/ellite/Wallos/commit/4da5d47e3ce8b8564921c07e7b785a367d378d6b))

## [2.11.1](https://github.com/ellite/Wallos/compare/v2.11.0...v2.11.1) (2024-06-30)


### Bug Fixes

* syntax error on svg logo ([#417](https://github.com/ellite/Wallos/issues/417)) ([b82f750](https://github.com/ellite/Wallos/commit/b82f750c8e844012a8a12e33f01719f42199e7ce))

## [2.11.0](https://github.com/ellite/Wallos/compare/v2.10.0...v2.11.0) (2024-06-30)


### Features

* theming engine custom colors now affect icons as well ([83e2066](https://github.com/ellite/Wallos/commit/83e2066e7bee99a152cc3c22f5b1dd9c9866c9fd))

## [2.10.0](https://github.com/ellite/Wallos/compare/v2.9.0...v2.10.0) (2024-06-27)


### Features

* add purple theme ([4d74c04](https://github.com/ellite/Wallos/commit/4d74c04f0e5bab5e1ece7a4a666f14d4a221fba6))


### Bug Fixes

* file name on ics export for subscriptions with non-ascii characters ([4d74c04](https://github.com/ellite/Wallos/commit/4d74c04f0e5bab5e1ece7a4a666f14d4a221fba6))

## [2.9.0](https://github.com/ellite/Wallos/compare/v2.8.0...v2.9.0) (2024-06-26)


### Features

* create users from the admin page ([#409](https://github.com/ellite/Wallos/issues/409)) ([6d2ffa6](https://github.com/ellite/Wallos/commit/6d2ffa6312b05f308117f2686681e2fcfaf734ec))

## [2.8.0](https://github.com/ellite/Wallos/compare/v2.7.0...v2.8.0) (2024-06-26)


### Features

* also show previous payments on the calendar for the current month ([c2e85d6](https://github.com/ellite/Wallos/commit/c2e85d6e109d9d07cc2fdbcb09b51564d1f73341))
* support automatic dark mode ([c2e85d6](https://github.com/ellite/Wallos/commit/c2e85d6e109d9d07cc2fdbcb09b51564d1f73341))


### Bug Fixes

* not every payment cycle was shown on the calendar ([c2e85d6](https://github.com/ellite/Wallos/commit/c2e85d6e109d9d07cc2fdbcb09b51564d1f73341))

## [2.7.0](https://github.com/ellite/Wallos/compare/v2.6.1...v2.7.0) (2024-06-25)


### Features

* export subscription as ics from the calendar view ([#404](https://github.com/ellite/Wallos/issues/404)) ([f1360f7](https://github.com/ellite/Wallos/commit/f1360f7d468ef5ae7e974ec1f9bb77831ea322bb))

## [2.6.1](https://github.com/ellite/Wallos/compare/v2.6.0...v2.6.1) (2024-06-25)


### Bug Fixes

* load php calendar extension ([#402](https://github.com/ellite/Wallos/issues/402)) ([c02ac77](https://github.com/ellite/Wallos/commit/c02ac770d7ac9fad1baec526b5d7dd71deaba59b))

## [2.6.0](https://github.com/ellite/Wallos/compare/v2.5.2...v2.6.0) (2024-06-25)


### Features

* add calendar view ([#399](https://github.com/ellite/Wallos/issues/399)) ([369f1a2](https://github.com/ellite/Wallos/commit/369f1a2bdcd9bdf3996b3dc8de8921f8954a069d))

## [2.5.2](https://github.com/ellite/Wallos/compare/v2.5.1...v2.5.2) (2024-06-24)


### Bug Fixes

* add ability to run container as an arbitrary user ([#396](https://github.com/ellite/Wallos/issues/396)) ([86fe2f3](https://github.com/ellite/Wallos/commit/86fe2f3ebb9c38ac34eaccd144a9550b7b314138))

## [2.5.1](https://github.com/ellite/Wallos/compare/v2.5.0...v2.5.1) (2024-06-21)


### Bug Fixes

* ntfy notifications ([#394](https://github.com/ellite/Wallos/issues/394)) ([17722c3](https://github.com/ellite/Wallos/commit/17722c31e31eec035d8896566e9eb5596951d022))

## [2.5.0](https://github.com/ellite/Wallos/compare/v2.4.2...v2.5.0) (2024-06-21)


### Features

* add option to clone subscription ([8304ed7](https://github.com/ellite/Wallos/commit/8304ed7b54f50ed7fa5ab520ff4d8d54f3ef34df))
* edit and delete options now available directly on the subscription list ([8304ed7](https://github.com/ellite/Wallos/commit/8304ed7b54f50ed7fa5ab520ff4d8d54f3ef34df))


### Bug Fixes

* typo on webhook payload ([8304ed7](https://github.com/ellite/Wallos/commit/8304ed7b54f50ed7fa5ab520ff4d8d54f3ef34df))

## [2.4.2](https://github.com/ellite/Wallos/compare/v2.4.1...v2.4.2) (2024-06-10)


### Bug Fixes

* update exchange cron only working for one user ([#384](https://github.com/ellite/Wallos/issues/384)) ([815eea7](https://github.com/ellite/Wallos/commit/815eea7e7be37e068e6173c229eb285ed8b7c30d))

## [2.4.1](https://github.com/ellite/Wallos/compare/v2.4.0...v2.4.1) (2024-06-09)


### Bug Fixes

* cronjob exchange update would not work with apilayer ([#381](https://github.com/ellite/Wallos/issues/381)) ([b0b4b7a](https://github.com/ellite/Wallos/commit/b0b4b7a65cd479e7532e72e826d3c01aead403c3))

## [2.4.0](https://github.com/ellite/Wallos/compare/v2.3.0...v2.4.0) (2024-06-07)


### Features

* add hability to disable login ([#378](https://github.com/ellite/Wallos/issues/378)) ([092be22](https://github.com/ellite/Wallos/commit/092be22183359f714fc9638d9013b742da828ed6))

## [2.3.0](https://github.com/ellite/Wallos/compare/v2.2.0...v2.3.0) (2024-06-05)


### Features

* add ntfy as notification method ([#377](https://github.com/ellite/Wallos/issues/377)) ([65edf09](https://github.com/ellite/Wallos/commit/65edf0963b73deff0f0f7f04427e69ce335bd776))


### Bug Fixes

* custom headers for webhook notifications ([#375](https://github.com/ellite/Wallos/issues/375)) ([7217088](https://github.com/ellite/Wallos/commit/7217088bb0732735a65322bce136d7d556b1acf3))

## [2.2.0](https://github.com/ellite/Wallos/compare/v2.1.0...v2.2.0) (2024-06-04)


### Features

* change filename of backup file ([fa99a73](https://github.com/ellite/Wallos/commit/fa99a735cd23918bab95baaf13b7a3142946d4b2))
* frequency is now up to 366 ([fa99a73](https://github.com/ellite/Wallos/commit/fa99a735cd23918bab95baaf13b7a3142946d4b2))


### Bug Fixes

* add webp support to gd on the container ([fa99a73](https://github.com/ellite/Wallos/commit/fa99a735cd23918bab95baaf13b7a3142946d4b2))
* translate: "no category" ([fa99a73](https://github.com/ellite/Wallos/commit/fa99a735cd23918bab95baaf13b7a3142946d4b2))
* trim fixer api key ([fa99a73](https://github.com/ellite/Wallos/commit/fa99a735cd23918bab95baaf13b7a3142946d4b2))
* update slovanian translations ([fa99a73](https://github.com/ellite/Wallos/commit/fa99a735cd23918bab95baaf13b7a3142946d4b2))

## [2.1.0](https://github.com/ellite/Wallos/compare/v2.0.0...v2.1.0) (2024-05-27)


### Features

* add slovenian translation ([03ceb8a](https://github.com/ellite/Wallos/commit/03ceb8a6e64c8cd4deb4019668fbf98acb57c5fe))


### Bug Fixes

* currency conversion failing on the statistics page ([03ceb8a](https://github.com/ellite/Wallos/commit/03ceb8a6e64c8cd4deb4019668fbf98acb57c5fe))

## [2.0.0](https://github.com/ellite/Wallos/compare/v1.29.1...v2.0.0) (2024-05-26)


### ⚠ BREAKING CHANGES

* allow registration of multiple users ([#340](https://github.com/ellite/Wallos/issues/340))

### Features

* add reset password functionality ([e1006e5](https://github.com/ellite/Wallos/commit/e1006e582388a7fab204f25c100347607b863e4e))
* administration area ([e1006e5](https://github.com/ellite/Wallos/commit/e1006e582388a7fab204f25c100347607b863e4e))
* allow registration of multiple users ([#340](https://github.com/ellite/Wallos/issues/340)) ([e1006e5](https://github.com/ellite/Wallos/commit/e1006e582388a7fab204f25c100347607b863e4e))

## [1.29.1](https://github.com/ellite/Wallos/compare/v1.29.0...v1.29.1) (2024-05-20)


### Bug Fixes

* calling htmlspecialchars_decode on null objects ([#338](https://github.com/ellite/Wallos/issues/338)) ([5050a28](https://github.com/ellite/Wallos/commit/5050a28f0e64e8c1eefb4f7cca8f6f6e473177e3))

## [1.29.0](https://github.com/ellite/Wallos/compare/v1.28.0...v1.29.0) (2024-05-20)


### Features

* subscriptions have personalized notification times ([#334](https://github.com/ellite/Wallos/issues/334)) ([c7146df](https://github.com/ellite/Wallos/commit/c7146dfd08c2a60d4ff6f7ac1f7cf5830fe28d9c))

## [1.28.0](https://github.com/ellite/Wallos/compare/v1.27.2...v1.28.0) (2024-05-17)


### Features

* add monthly budget field and statistics ([#329](https://github.com/ellite/Wallos/issues/329)) ([b622434](https://github.com/ellite/Wallos/commit/b622434ca0791d5c8026d641e1b32f8a2f0f42b8))

## [1.27.2](https://github.com/ellite/Wallos/compare/v1.27.1...v1.27.2) (2024-05-17)


### Bug Fixes

* duplicated messages on discord notifications ([d44b40b](https://github.com/ellite/Wallos/commit/d44b40b0ce80e91821fe7441c85e0d8794680618))
* possible division by 0 on statistics page ([d44b40b](https://github.com/ellite/Wallos/commit/d44b40b0ce80e91821fe7441c85e0d8794680618))

## [1.27.1](https://github.com/ellite/Wallos/compare/v1.27.0...v1.27.1) (2024-05-13)


### Bug Fixes

* import of translations for cronjobs was missing ([#321](https://github.com/ellite/Wallos/issues/321)) ([a524419](https://github.com/ellite/Wallos/commit/a524419e0a468147a2094dba81689dd643a0108b))

## [1.27.0](https://github.com/ellite/Wallos/compare/v1.26.2...v1.27.0) (2024-05-11)


### Features

* add korean translation ([#314](https://github.com/ellite/Wallos/issues/314)) ([bc40320](https://github.com/ellite/Wallos/commit/bc403206905b39c3aa88f3eb51e59b41e2a5e24e))

## [1.26.2](https://github.com/ellite/Wallos/compare/v1.26.1...v1.26.2) (2024-05-09)


### Bug Fixes

* russian translations ([#309](https://github.com/ellite/Wallos/issues/309)) ([8f890fc](https://github.com/ellite/Wallos/commit/8f890fc5d3a62a91feec50564179b3241ed538bf))

## [1.26.1](https://github.com/ellite/Wallos/compare/v1.26.0...v1.26.1) (2024-05-09)


### Bug Fixes

* background removal experimental setting ([#307](https://github.com/ellite/Wallos/issues/307)) ([bb5ee2e](https://github.com/ellite/Wallos/commit/bb5ee2e64c11b1415da3aa50119dfaa3783be37f))

## [1.26.0](https://github.com/ellite/Wallos/compare/v1.25.1...v1.26.0) (2024-05-08)


### Features

* add russian translation ([#305](https://github.com/ellite/Wallos/issues/305)) ([ae04d50](https://github.com/ellite/Wallos/commit/ae04d50329c1fb0117e186f89fef38b495cbbe9c))

## [1.25.1](https://github.com/ellite/Wallos/compare/v1.25.0...v1.25.1) (2024-05-07)


### Bug Fixes

* broken discord form ([#302](https://github.com/ellite/Wallos/issues/302)) ([b435d6a](https://github.com/ellite/Wallos/commit/b435d6a5cf6f80404c487b519334b2854aab9713))

## [1.25.0](https://github.com/ellite/Wallos/compare/v1.24.0...v1.25.0) (2024-05-06)


### Features

* add discord and pushover as notification agents ([#300](https://github.com/ellite/Wallos/issues/300)) ([8994829](https://github.com/ellite/Wallos/commit/899482982e7e200f5a7081ed6285475e5cb2a37d))


### Bug Fixes

* most error messages of the notifications endpoints would not reach the frontend ([8994829](https://github.com/ellite/Wallos/commit/899482982e7e200f5a7081ed6285475e5cb2a37d))

## [1.24.0](https://github.com/ellite/Wallos/compare/v1.23.0...v1.24.0) (2024-05-05)


### Features

* add new notification methods (telegram, webhooks, gotify) ([#295](https://github.com/ellite/Wallos/issues/295)) ([a408031](https://github.com/ellite/Wallos/commit/a408031ef8711bf87e9f8db35f52c498f250b235))

## [1.23.0](https://github.com/ellite/Wallos/compare/v1.22.0...v1.23.0) (2024-04-26)


### Features

* backup and restore ([#288](https://github.com/ellite/Wallos/issues/288)) ([7b509d2](https://github.com/ellite/Wallos/commit/7b509d2b3d769e14a9cb4fd183395dcecc9d993b))

## [1.22.0](https://github.com/ellite/Wallos/compare/v1.21.1...v1.22.0) (2024-04-20)


### Features

* option to hide disabled subscriptions ([#286](https://github.com/ellite/Wallos/issues/286)) ([b80ab4b](https://github.com/ellite/Wallos/commit/b80ab4bdc662c3e80a2fd42b8b286b69beac441c))

## [1.21.1](https://github.com/ellite/Wallos/compare/v1.21.0...v1.21.1) (2024-04-19)


### Bug Fixes

* small layout issues ([769f8a0](https://github.com/ellite/Wallos/commit/769f8a0587941bffd0d7463b7e7ffeb38a70e301))

## [1.21.0](https://github.com/ellite/Wallos/compare/v1.20.2...v1.21.0) (2024-04-19)


### Features

* add italian translation ([70e4234](https://github.com/ellite/Wallos/commit/70e42349caee5d6647b6b704643fe2b5e26dff4e))
* add themes and custom color options ([70e4234](https://github.com/ellite/Wallos/commit/70e42349caee5d6647b6b704643fe2b5e26dff4e))

## [1.20.2](https://github.com/ellite/Wallos/compare/v1.20.1...v1.20.2) (2024-04-11)


### Bug Fixes

* encoding for url and notes ([#273](https://github.com/ellite/Wallos/issues/273)) ([ad86eb5](https://github.com/ellite/Wallos/commit/ad86eb5b9c6e60004de2795170032d62b33ddcfb))

## [1.20.1](https://github.com/ellite/Wallos/compare/v1.20.0...v1.20.1) (2024-04-09)


### Bug Fixes

* special chars in subscriptions ([#271](https://github.com/ellite/Wallos/issues/271)) ([2683a7c](https://github.com/ellite/Wallos/commit/2683a7c4ba3c3575347d48f2c97b92b2ff0cc9f9))

## [1.20.0](https://github.com/ellite/Wallos/compare/v1.19.0...v1.20.0) (2024-04-07)


### Features

* add serbian translation ([#268](https://github.com/ellite/Wallos/issues/268)) ([55089c0](https://github.com/ellite/Wallos/commit/55089c0715ca315feb6a8795b07d9c36167494de))

## [1.19.0](https://github.com/ellite/Wallos/compare/v1.18.3...v1.19.0) (2024-04-03)


### Features

* add polish translation ([#263](https://github.com/ellite/Wallos/issues/263)) ([c752761](https://github.com/ellite/Wallos/commit/c7527610fafa49b18076971befa246b2730b79c4))

## [1.18.3](https://github.com/ellite/Wallos/compare/v1.18.2...v1.18.3) (2024-03-30)


### Bug Fixes

* on initial registration page, logo can be cut off ([#258](https://github.com/ellite/Wallos/issues/258)) ([dde8695](https://github.com/ellite/Wallos/commit/dde8695fb555f483ef8bc8f24db2a610301bab16))

## [1.18.2](https://github.com/ellite/Wallos/compare/v1.18.1...v1.18.2) (2024-03-28)


### Bug Fixes

* small icon size for payment icons ([#253](https://github.com/ellite/Wallos/issues/253)) ([8998e23](https://github.com/ellite/Wallos/commit/8998e23d370165ca158600550dbf0eb8c07d4bac))

## [1.18.1](https://github.com/ellite/Wallos/compare/v1.18.0...v1.18.1) (2024-03-25)


### Bug Fixes

* disabled inputs on dark theme ([#250](https://github.com/ellite/Wallos/issues/250)) ([11f0e7c](https://github.com/ellite/Wallos/commit/11f0e7ce63f37adb922e530a54f3e5cc9f640eee))

## [1.18.0](https://github.com/ellite/Wallos/compare/v1.17.3...v1.18.0) (2024-03-24)


### Features

* add custom avatar functionality ([#248](https://github.com/ellite/Wallos/issues/248)) ([1dbebd3](https://github.com/ellite/Wallos/commit/1dbebd3918ef6f27961f4e70b6ad007133f8ff93))

## [1.17.3](https://github.com/ellite/Wallos/compare/v1.17.2...v1.17.3) (2024-03-20)


### Bug Fixes

* next payment date not updating for disabled subscriptions ([#243](https://github.com/ellite/Wallos/issues/243)) ([75a5672](https://github.com/ellite/Wallos/commit/75a5672de32a59cc53c3c76a08793e6a33cce828))

## [1.17.2](https://github.com/ellite/Wallos/compare/v1.17.1...v1.17.2) (2024-03-18)


### Bug Fixes

* pwa not loading static files when offline ([#241](https://github.com/ellite/Wallos/issues/241)) ([4e3376d](https://github.com/ellite/Wallos/commit/4e3376df93ea7c2b3e184b2670ebe77fe9b15d6a))

## [1.17.1](https://github.com/ellite/Wallos/compare/v1.17.0...v1.17.1) (2024-03-18)


### Bug Fixes

* cronjobs running twice ([#239](https://github.com/ellite/Wallos/issues/239)) ([00cbf8d](https://github.com/ellite/Wallos/commit/00cbf8d9e3feac87292630f8db4571a99b542db4))

## [1.17.0](https://github.com/ellite/Wallos/compare/v1.16.3...v1.17.0) (2024-03-17)


### Features

* allow selecting tls or ssl for email notifications ([#237](https://github.com/ellite/Wallos/issues/237)) ([2462435](https://github.com/ellite/Wallos/commit/246243574328ead6d95d45b81b055761b01040a7))

## [1.16.3](https://github.com/ellite/Wallos/compare/v1.16.2...v1.16.3) (2024-03-17)


### Bug Fixes

* allow redirects on logo search ([ae73db7](https://github.com/ellite/Wallos/commit/ae73db77907786993f52f7273145dafa660c4d36))
* rename category after adding and sort order of categories ([ae73db7](https://github.com/ellite/Wallos/commit/ae73db77907786993f52f7273145dafa660c4d36))

## [1.16.2](https://github.com/ellite/Wallos/compare/v1.16.1...v1.16.2) (2024-03-13)


### Bug Fixes

* wrong folder for payment method logos ([#227](https://github.com/ellite/Wallos/issues/227)) ([f6c1ff2](https://github.com/ellite/Wallos/commit/f6c1ff2a6be6545c6c179722235db3cd724127fd))

## [1.16.1](https://github.com/ellite/Wallos/compare/v1.16.0...v1.16.1) (2024-03-12)


### Bug Fixes

* confusing wording for billing cycle ([94ad0cb](https://github.com/ellite/Wallos/commit/94ad0cb553d7f05b15e9ab27fbf4c26955fc3ff1))

## [1.16.0](https://github.com/ellite/Wallos/compare/v1.15.3...v1.16.0) (2024-03-10)


### Features

* allow sorting payment methods ([#217](https://github.com/ellite/Wallos/issues/217)) ([aef2d13](https://github.com/ellite/Wallos/commit/aef2d134c22f7dc95821ff711f7bca56228bfed6))
* don't allow to change currency code if in use ([aef2d13](https://github.com/ellite/Wallos/commit/aef2d134c22f7dc95821ff711f7bca56228bfed6))

## [1.15.3](https://github.com/ellite/Wallos/compare/v1.15.2...v1.15.3) (2024-03-10)


### Bug Fixes

* sql injection vulnerability when using filters ([#214](https://github.com/ellite/Wallos/issues/214)) ([cbdc188](https://github.com/ellite/Wallos/commit/cbdc188e5e7a2c357f5b0bcaeaf2e886cd2555e3))

## [1.15.2](https://github.com/ellite/Wallos/compare/v1.15.1...v1.15.2) (2024-03-09)


### Bug Fixes

* undefined var on the statistics page ([#211](https://github.com/ellite/Wallos/issues/211)) ([8b7a7b9](https://github.com/ellite/Wallos/commit/8b7a7b94e3ae9177be6d067d8fee0a05aa428f4a))

## [1.15.1](https://github.com/ellite/Wallos/compare/v1.15.0...v1.15.1) (2024-03-09)


### Bug Fixes

* undefined var if sort cookie is not set ([#207](https://github.com/ellite/Wallos/issues/207)) ([288c106](https://github.com/ellite/Wallos/commit/288c10624592aa04cc76cb8ae066331d65964650))

## [1.15.0](https://github.com/ellite/Wallos/compare/v1.14.1...v1.15.0) (2024-03-09)


### Features

* filters on the subscriptions page ([a396285](https://github.com/ellite/Wallos/commit/a396285b76cd87e598495f311a81dc68a7f66d36))
* search subscriptions by name ([a396285](https://github.com/ellite/Wallos/commit/a396285b76cd87e598495f311a81dc68a7f66d36))

## [1.14.1](https://github.com/ellite/Wallos/compare/v1.14.0...v1.14.1) (2024-03-08)


### Bug Fixes

* wrong message when deleting payment methods ([#202](https://github.com/ellite/Wallos/issues/202)) ([93a3d18](https://github.com/ellite/Wallos/commit/93a3d189794985c1d8cfd5558c482f66e79405a8))

## [1.14.0](https://github.com/ellite/Wallos/compare/v1.13.0...v1.14.0) (2024-03-08)


### Features

* add brazilian portuguese to available languages ([#198](https://github.com/ellite/Wallos/issues/198)) ([3ea9d98](https://github.com/ellite/Wallos/commit/3ea9d98da79e9b13ab9d93a56b89062ac19c31d7))

## [1.13.0](https://github.com/ellite/Wallos/compare/v1.12.1...v1.13.0) (2024-03-07)


### Features

* show name of most expensive subscription on statistics ([#194](https://github.com/ellite/Wallos/issues/194)) ([ede08b1](https://github.com/ellite/Wallos/commit/ede08b1f6ae2d52ac0f8e1aaa77edc1924f529ce))

## [1.12.1](https://github.com/ellite/Wallos/compare/v1.12.0...v1.12.1) (2024-03-06)


### Bug Fixes

* broken chinese language file ([#192](https://github.com/ellite/Wallos/issues/192)) ([94c1a91](https://github.com/ellite/Wallos/commit/94c1a91387ca05fad3a50e5f318d8439c7608cbe))

## [1.12.0](https://github.com/ellite/Wallos/compare/v1.11.3...v1.12.0) (2024-03-05)


### Features

* add filters to statistics page ([83234ab](https://github.com/ellite/Wallos/commit/83234ab8cd184f4693a148dc55bddef300c49e71))
* allow deletion of the default payment methods ([83234ab](https://github.com/ellite/Wallos/commit/83234ab8cd184f4693a148dc55bddef300c49e71))
* allow renaming / translation of payment methods ([83234ab](https://github.com/ellite/Wallos/commit/83234ab8cd184f4693a148dc55bddef300c49e71))
* allow sorting of categories in settings ([83234ab](https://github.com/ellite/Wallos/commit/83234ab8cd184f4693a148dc55bddef300c49e71))

## [1.11.3](https://github.com/ellite/Wallos/compare/v1.11.2...v1.11.3) (2024-03-02)


### Bug Fixes

* redirects with the service worker ([#183](https://github.com/ellite/Wallos/issues/183)) ([940bbbe](https://github.com/ellite/Wallos/commit/940bbbea9071a7c2687a3340bb8e9d6f4f884cc1))

## [1.11.2](https://github.com/ellite/Wallos/compare/v1.11.1...v1.11.2) (2024-03-02)


### Bug Fixes

* file upload bypass vulnerability ([#181](https://github.com/ellite/Wallos/issues/181)) ([0f7853f](https://github.com/ellite/Wallos/commit/0f7853f961ba2f68f8dcd358acaad6c6eb7980e6))

## [1.11.1](https://github.com/ellite/Wallos/compare/v1.11.0...v1.11.1) (2024-03-01)


### Bug Fixes

* security issue with image upload ([#175](https://github.com/ellite/Wallos/issues/175)) ([7b5e166](https://github.com/ellite/Wallos/commit/7b5e166e289f32b1b3451614b16e1f4c0b9d6f2a))

## [1.11.0](https://github.com/ellite/Wallos/compare/v1.10.0...v1.11.0) (2024-03-01)


### Features

* added custom payment methods ([#173](https://github.com/ellite/Wallos/issues/173)) ([e739622](https://github.com/ellite/Wallos/commit/e73962260678caf0843b6302f7fbb7d49469a1a9))

## [1.10.0](https://github.com/ellite/Wallos/compare/v1.9.1...v1.10.0) (2024-02-29)


### Features

* use brave search for the logos if google fails ([#169](https://github.com/ellite/Wallos/issues/169)) ([fff783e](https://github.com/ellite/Wallos/commit/fff783e4e87f04199817c7cb3b4bd28760d2b5f3))

## [1.9.1](https://github.com/ellite/Wallos/compare/v1.9.0...v1.9.1) (2024-02-28)


### Bug Fixes

* move display settings to the bottom ([ec25d4b](https://github.com/ellite/Wallos/commit/ec25d4bc5a35f68ff15d456ae6a1d3e98d124f5f))
* reorder subscription form ([ec25d4b](https://github.com/ellite/Wallos/commit/ec25d4bc5a35f68ff15d456ae6a1d3e98d124f5f))
* show email field on adding household member ([ec25d4b](https://github.com/ellite/Wallos/commit/ec25d4bc5a35f68ff15d456ae6a1d3e98d124f5f))

## [1.9.0](https://github.com/ellite/Wallos/compare/v1.8.3...v1.9.0) (2024-02-27)


### Features

* enable progressive web app ([a2a315e](https://github.com/ellite/Wallos/commit/a2a315e34dca2562bc11793cc5841c2082e811a9))


### Bug Fixes

* update packages to fix vulnerabilities ([a2a315e](https://github.com/ellite/Wallos/commit/a2a315e34dca2562bc11793cc5841c2082e811a9))

## [1.8.3](https://github.com/ellite/Wallos/compare/v1.8.2...v1.8.3) (2024-02-26)


### Bug Fixes

* remove service worker ([#157](https://github.com/ellite/Wallos/issues/157)) ([5ccadce](https://github.com/ellite/Wallos/commit/5ccadce2f139e5873889badc51a67bfaef8a9304))

## [1.8.2](https://github.com/ellite/Wallos/compare/v1.8.1...v1.8.2) (2024-02-26)


### Bug Fixes

* service worker redirect not set to follow ([3640b54](https://github.com/ellite/Wallos/commit/3640b547ee3ca28e7b872b9e2dbbcd1d31c54953))

## [1.8.1](https://github.com/ellite/Wallos/compare/v1.8.0...v1.8.1) (2024-02-26)


### Bug Fixes

* service worker has redirections ([4aca7bc](https://github.com/ellite/Wallos/commit/4aca7bcb3cdbb77958db8783c4f088df131db645))

## [1.8.0](https://github.com/ellite/Wallos/compare/v1.7.0...v1.8.0) (2024-02-26)


### Features

* convert wallos into a progressive web app ([#151](https://github.com/ellite/Wallos/issues/151)) ([19e2058](https://github.com/ellite/Wallos/commit/19e205897617ee894d8802f7e73fef46be386c30))


### Bug Fixes

* improve traditional chinese translations ([19e2058](https://github.com/ellite/Wallos/commit/19e205897617ee894d8802f7e73fef46be386c30))

## [1.7.0](https://github.com/ellite/Wallos/compare/v1.6.0...v1.7.0) (2024-02-25)


### Features

* add email for notifications to household members ([26363dd](https://github.com/ellite/Wallos/commit/26363dd5f364b5494c526a9769626b03bba45273))

## [1.6.0](https://github.com/ellite/Wallos/compare/v1.5.0...v1.6.0) (2024-02-24)


### Features

* add stats about inactive subscriptions ([#146](https://github.com/ellite/Wallos/issues/146)) ([ccac17a](https://github.com/ellite/Wallos/commit/ccac17a6f222cb1ee022fd30b7a1d34306dd0de2))
* sort disabled subscription at the bottom ([ccac17a](https://github.com/ellite/Wallos/commit/ccac17a6f222cb1ee022fd30b7a1d34306dd0de2))

## [1.5.0](https://github.com/ellite/Wallos/compare/v1.4.1...v1.5.0) (2024-02-23)


### Features

* allow to disable subscriptions ([#144](https://github.com/ellite/Wallos/issues/144)) ([50056d9](https://github.com/ellite/Wallos/commit/50056d9f03a46c166650474b3877b55a24873bb9))

## [1.4.1](https://github.com/ellite/Wallos/compare/v1.4.0...v1.4.1) (2024-02-22)


### Bug Fixes

* bug on saving fixer api key ([#142](https://github.com/ellite/Wallos/issues/142)) ([866eb28](https://github.com/ellite/Wallos/commit/866eb28e88495e851336b5e224274a823ff4173d))

## [1.4.0](https://github.com/ellite/Wallos/compare/v1.3.1...v1.4.0) (2024-02-21)


### Features

* persist display and experimental settings on the db ([f0a6f1a](https://github.com/ellite/Wallos/commit/f0a6f1a2f18b329c9f784a9f1953cd0e7616e1c6))
* small styles changed ([f0a6f1a](https://github.com/ellite/Wallos/commit/f0a6f1a2f18b329c9f784a9f1953cd0e7616e1c6))

## [1.3.1](https://github.com/ellite/Wallos/compare/v1.3.0...v1.3.1) (2024-02-20)


### Bug Fixes

* missing authentication check ([#133](https://github.com/ellite/Wallos/issues/133)) ([b887d3a](https://github.com/ellite/Wallos/commit/b887d3a0503585dadde4b1b59b023c981b0f7f66))

## [1.3.0](https://github.com/ellite/Wallos/compare/v1.2.0...v1.3.0) (2024-02-19)


### Features

* add apilayer as provider for fixer api ([0f19dd6](https://github.com/ellite/Wallos/commit/0f19dd688fe3a2156e7d26d1bf1e1f8b30ce79ad))
* add apilayer as provider for fixer api ([#127](https://github.com/ellite/Wallos/issues/127)) ([0f19dd6](https://github.com/ellite/Wallos/commit/0f19dd688fe3a2156e7d26d1bf1e1f8b30ce79ad))
* update exchange rate when saving api key ([0f19dd6](https://github.com/ellite/Wallos/commit/0f19dd688fe3a2156e7d26d1bf1e1f8b30ce79ad))

## [1.2.0](https://github.com/ellite/Wallos/compare/v1.1.0...v1.2.0) (2024-02-19)


### Features

* enable deployment in subdirectory ([e2af9af](https://github.com/ellite/Wallos/commit/e2af9afc32bfc248f594336c50d44ad6f36f197e))

## [1.1.0](https://github.com/ellite/Wallos/compare/v1.0.1...v1.1.0) (2024-02-18)


### Features

* new statistics per payment method ([#124](https://github.com/ellite/Wallos/issues/124)) ([6200fa5](https://github.com/ellite/Wallos/commit/6200fa5e87d3f60853c3d8b95f5d676e39b378f4))

## [1.0.1](https://github.com/ellite/Wallos/compare/v1.0.0...v1.0.1) (2024-02-18)


### Bug Fixes

* show translated no category when sorting by category ([#122](https://github.com/ellite/Wallos/issues/122)) ([330c061](https://github.com/ellite/Wallos/commit/330c061b74ad1580173f3d3bc7b14048492e22d2))

## 1.0.0 (2024-02-15)


### Features

* add workflow for building and publishing docker images ([970c96a](https://github.com/ellite/Wallos/commit/970c96a8c904809544c944071986be2a684daf50))
* specify image stability type when triggering build ([5b22cfd](https://github.com/ellite/Wallos/commit/5b22cfd87a94a865f53b282964961862bbea1861))


### Bug Fixes

* Currency not preselected on registration ([fc56cf6](https://github.com/ellite/Wallos/commit/fc56cf69ef22a07978022265b2e8344dc293eb14))
* Language sort order ([884a8e5](https://github.com/ellite/Wallos/commit/884a8e569339ddbcb89af4634c0c845b053affbb))
