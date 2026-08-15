# Changelog

## [0.25.2](https://github.com/bambamboole/spectacular/compare/0.25.1...0.25.2) (2026-08-15)


### Bug Fixes

* **openapi:** drop the dotted rule leftovers of a morphable collection ([#81](https://github.com/bambamboole/spectacular/issues/81)) ([8f5e1e0](https://github.com/bambamboole/spectacular/commit/8f5e1e08eb43634357699b7d59d045bf57f99210))

## [0.25.1](https://github.com/bambamboole/spectacular/compare/0.25.0...0.25.1) (2026-08-15)


### Bug Fixes

* **openapi:** pin a oneOf discriminator by replacing its node ([#79](https://github.com/bambamboole/spectacular/issues/79)) ([7341484](https://github.com/bambamboole/spectacular/commit/734148413b58f3b20f9d2483651b29ba81867849))

## [0.25.0](https://github.com/bambamboole/spectacular/compare/0.24.0...0.25.0) (2026-08-15)


### Features

* **openapi:** document property-morphable data as a discriminated oneOf ([#77](https://github.com/bambamboole/spectacular/issues/77)) ([a5660d2](https://github.com/bambamboole/spectacular/commit/a5660d2f6faade54a0ff42600105e652ec737a6b))

## [0.24.0](https://github.com/bambamboole/spectacular/compare/0.23.0...0.24.0) (2026-08-15)


### Features

* **openapi:** document dynamic operator filters honestly ([#73](https://github.com/bambamboole/spectacular/issues/73)) ([675785d](https://github.com/bambamboole/spectacular/commit/675785df263171922a0d74cf7d9c9e52e2246080))
* **openapi:** honest filter lists, between filters, and self-documenting custom filters ([#75](https://github.com/bambamboole/spectacular/issues/75)) ([b5a945c](https://github.com/bambamboole/spectacular/commit/b5a945c11ac74e13b9ea6a100b8f5a0197456cff))

## [0.23.0](https://github.com/bambamboole/spectacular/compare/0.22.0...0.23.0) (2026-08-15)


### Features

* **openapi:** type filters on BigDecimal-cast columns as number ([#71](https://github.com/bambamboole/spectacular/issues/71)) ([55b44d9](https://github.com/bambamboole/spectacular/commit/55b44d9662ab325a9802c69ced68876a08c8acae))

## [0.22.0](https://github.com/bambamboole/spectacular/compare/0.21.2...0.22.0) (2026-08-15)


### Features

* **laravel-data:** first-class BigDecimal properties ([#69](https://github.com/bambamboole/spectacular/issues/69)) ([f181d0d](https://github.com/bambamboole/spectacular/commit/f181d0de4d0fc7ae647d9e40401c65d87c5d510d))

## [0.21.2](https://github.com/bambamboole/spectacular/compare/0.21.1...0.21.2) (2026-08-15)


### Bug Fixes

* **openapi:** resolve the subject model from a builder with a static base ([#67](https://github.com/bambamboole/spectacular/issues/67)) ([071e09c](https://github.com/bambamboole/spectacular/commit/071e09c50bf00297290deefd77feda4b0fcd6c1b))

## [0.21.1](https://github.com/bambamboole/spectacular/compare/0.21.0...0.21.1) (2026-08-15)


### Bug Fixes

* **query-builder:** validate model API declarations on every chain-completing call ([#65](https://github.com/bambamboole/spectacular/issues/65)) ([2728b45](https://github.com/bambamboole/spectacular/commit/2728b45d2038da3c0a7cafa923660eb3df2d1711))

## [0.21.0](https://github.com/bambamboole/spectacular/compare/0.20.0...0.21.0) (2026-08-15)


### Features

* add model API query declarations ([#63](https://github.com/bambamboole/spectacular/issues/63)) ([d4848bb](https://github.com/bambamboole/spectacular/commit/d4848bbc38abe345c3e846209fbaaaf1fa55559a))

## [0.20.0](https://github.com/bambamboole/spectacular/compare/0.19.0...0.20.0) (2026-08-15)


### Features

* **query-builder:** public model filters and sorts, scope docblock descriptions ([#61](https://github.com/bambamboole/spectacular/issues/61)) ([b74df1f](https://github.com/bambamboole/spectacular/commit/b74df1f8ec03903f6baaacdc2c342ce731edeb61))

## [0.19.0](https://github.com/bambamboole/spectacular/compare/0.18.1...0.19.0) (2026-08-15)


### Features

* **openapi:** document a 422 on endpoints paginating with apiPaginate ([#59](https://github.com/bambamboole/spectacular/issues/59)) ([ec0078f](https://github.com/bambamboole/spectacular/commit/ec0078f99625c1330107b68ab25b27e88f207f91))


### Bug Fixes

* **openapi:** drop dotted rule properties once a nested data object is referenced ([#58](https://github.com/bambamboole/spectacular/issues/58)) ([b1eec5a](https://github.com/bambamboole/spectacular/commit/b1eec5a5fabcdad1016b2a0e583628d1ec48723c))

## [0.18.1](https://github.com/bambamboole/spectacular/compare/0.18.0...0.18.1) (2026-08-15)


### Bug Fixes

* **openapi:** nested laravel-data schemas and model-state filter typing ([#56](https://github.com/bambamboole/spectacular/issues/56)) ([e7bdbd6](https://github.com/bambamboole/spectacular/commit/e7bdbd698f9b1a8f17fc29e6c8c8820d6540a5ba))

## [0.18.0](https://github.com/bambamboole/spectacular/compare/0.17.0...0.18.0) (2026-08-14)


### ⚠ BREAKING CHANGES

* **model-states:** take the transition payload as a plain array ([#54](https://github.com/bambamboole/spectacular/issues/54))

### Features

* **model-states:** take the transition payload as a plain array ([#54](https://github.com/bambamboole/spectacular/issues/54)) ([a8096ce](https://github.com/bambamboole/spectacular/commit/a8096ceabd83008cd2e01ea865b6dfc5f41a6e1f))

## [0.17.0](https://github.com/bambamboole/spectacular/compare/0.16.0...0.17.0) (2026-08-14)


### ⚠ BREAKING CHANGES

* **openapi:** derive state transition endpoints from routes, drop the config

### Features

* **openapi:** declare state transition endpoints with a StateEndpoint attribute ([c6f7088](https://github.com/bambamboole/spectacular/commit/c6f7088b56d54d160d6a4952807f6ca6ff29a5fb))
* **openapi:** derive state transition endpoints from routes, drop the config ([f1fd8cd](https://github.com/bambamboole/spectacular/commit/f1fd8cd7c22bce6d349ced3635dffc61bea6f91b))
* **openapi:** state transition endpoints for spatie model states ([65c652c](https://github.com/bambamboole/spectacular/commit/65c652c4428374f9ee16bf8686fb8162463ba8dd))

## [0.16.0](https://github.com/bambamboole/spectacular/compare/0.15.0...0.16.0) (2026-08-13)


### Features

* **openapi:** document spatie model states as string enum schemas ([ae33e81](https://github.com/bambamboole/spectacular/commit/ae33e814f537894c76bb5d0d164639ba7500851c))

## [0.15.0](https://github.com/bambamboole/spectacular/compare/0.14.0...0.15.0) (2026-08-13)


### Features

* **openapi:** documentation attributes with HTML tooltips ([19b8eea](https://github.com/bambamboole/spectacular/commit/19b8eeac465d72bb4b547ba5ed1796b58773d2da))

## [0.14.0](https://github.com/bambamboole/spectacular/compare/0.13.1...0.14.0) (2026-08-12)


### Features

* **openapi:** type filters, document rate limits and the full info object ([fbe5cb3](https://github.com/bambamboole/spectacular/commit/fbe5cb3ba68f149117ec2bc828bd6dc21b396112))

## [0.13.1](https://github.com/bambamboole/spectacular/compare/0.13.0...0.13.1) (2026-08-12)


### Bug Fixes

* **openapi:** configure scramble on boot instead of register ([182b3fc](https://github.com/bambamboole/spectacular/commit/182b3fc5ca8a9d9e3580fc76523a061c968c0dba))

## [0.13.0](https://github.com/bambamboole/spectacular/compare/0.12.0...0.13.0) (2026-08-12)


### Features

* **openapi:** declare authentication modes from configuration ([f3aaa01](https://github.com/bambamboole/spectacular/commit/f3aaa01f1902a54312401a13016a7b7f039a121d))
* **openapi:** document validation errors and laravel-data request bodies ([6ee06b9](https://github.com/bambamboole/spectacular/commit/6ee06b995716a0967f29d34d4ca62cfb3f93c80a))
* **openapi:** validation errors, laravel-data request bodies, configurable auth modes ([6eed186](https://github.com/bambamboole/spectacular/commit/6eed18662e2af34b8f2097d32f63bad71b3e8e8d))

## [0.12.0](https://github.com/bambamboole/spectacular/compare/0.11.0...0.12.0) (2026-08-10)


### Features

* add OpenAPI test validation ([25e2685](https://github.com/bambamboole/spectacular/commit/25e268577f0ccd3ec69281e60d494ca7a50649f2))
* add OpenAPI test validation ([9b68781](https://github.com/bambamboole/spectacular/commit/9b68781aea9c9d9a9c0f7b5ba6d0f64dc84fbe7a))

## [0.11.0](https://github.com/bambamboole/spectacular/compare/0.10.0...0.11.0) (2026-08-07)


### ⚠ BREAKING CHANGES

* extract the Lattice API reference into lattice-php/api-reference

### Code Refactoring

* extract the Lattice API reference into lattice-php/api-reference ([7e25515](https://github.com/bambamboole/spectacular/commit/7e25515b567fdfc22bb5b6be5de5ad7b5ca66555))

## [0.10.0](https://github.com/bambamboole/spectacular/compare/0.9.0...0.10.0) (2026-08-06)


### Miscellaneous Chores

* release lattice 0.41 upgrade ([dcb0709](https://github.com/bambamboole/spectacular/commit/dcb0709f9c792a9d6b24498624334ca0cea1acc6))

## [0.9.0](https://github.com/bambamboole/spectacular/compare/0.8.0...0.9.0) (2026-08-05)


### Miscellaneous Chores

* release 0.9.0 ([73d7e1e](https://github.com/bambamboole/spectacular/commit/73d7e1eb8fa1b364ad285f6291d5e655b8a11f04))

## [0.8.0](https://github.com/bambamboole/spectacular/compare/0.7.0...0.8.0) (2026-08-05)


### Features

* render JSON request bodies as fields ([b367557](https://github.com/bambamboole/spectacular/commit/b3675579b239dad479a150c5f5bde155bbe5d966))
* render JSON request bodies as fields ([1ee8ec9](https://github.com/bambamboole/spectacular/commit/1ee8ec9a7fb216be9c6f5b7b4286d9bd049453c4))


### Bug Fixes

* harden API reference authentication and contracts ([efd2b47](https://github.com/bambamboole/spectacular/commit/efd2b47e32b0344342793dcead288335935fdd4f))
* harden API reference interactions ([b5b06e1](https://github.com/bambamboole/spectacular/commit/b5b06e175864abd7dc273bcea28adad174abed6a))

## [0.7.0](https://github.com/bambamboole/spectacular/compare/0.6.0...0.7.0) (2026-08-04)


### Features

* group request parameters ([ff95a15](https://github.com/bambamboole/spectacular/commit/ff95a1597e0d5765ccd4fbae6ab9315600e03943))
* group request parameters ([3dad515](https://github.com/bambamboole/spectacular/commit/3dad5150efb7f5060cc74d762108885d8f33340a))

## [0.6.0](https://github.com/bambamboole/spectacular/compare/0.5.0...0.6.0) (2026-08-04)


### Features

* add selectable pagination modes ([70d4625](https://github.com/bambamboole/spectacular/commit/70d46259f0cba3024fa71fff70ecaf56a7645436))
* add selectable pagination query builder ([4370e93](https://github.com/bambamboole/spectacular/commit/4370e93e1cadab51e5b9ae560417407945ae67d8))
* document selectable pagination modes ([0ab6901](https://github.com/bambamboole/spectacular/commit/0ab6901e4febe66c3ab1db757e14545226ce091b))
* group pagination request controls ([050d923](https://github.com/bambamboole/spectacular/commit/050d9234f27abb59ad52a41e2e380357fd5f1ea7))


### Bug Fixes

* detect standalone pagination controls ([d8484a1](https://github.com/bambamboole/spectacular/commit/d8484a1ec5d6d271c126f24e4e494303625d9283))
* handle named pagination arguments ([bd1db1f](https://github.com/bambamboole/spectacular/commit/bd1db1f899425d82d914d8267f1b975c065c5d9d))
* prevent pagination mode field growth ([3f45352](https://github.com/bambamboole/spectacular/commit/3f45352d708bf4be2000f9071bf0b5282dbb7430))
* stop documenting allowed fields ([5d6d005](https://github.com/bambamboole/spectacular/commit/5d6d0051f4c39755eb72624b5c2538b7e0a8c156))
* stop documenting allowed fields ([786427b](https://github.com/bambamboole/spectacular/commit/786427b8ad7920f776c3233333fd2a5f4662bfc9))

## [0.5.0](https://github.com/bambamboole/spectacular/compare/0.4.0...0.5.0) (2026-08-04)


### Features

* improve responsive api reference layout ([aee9035](https://github.com/bambamboole/spectacular/commit/aee90355a1f2ead00d2299e6ff55abf183a67048))


### Bug Fixes

* improve responsive operation headers ([629b802](https://github.com/bambamboole/spectacular/commit/629b802983061e58776be2104c0d6275179236ea))

## [0.4.0](https://github.com/bambamboole/spectacular/compare/0.3.0...0.4.0) (2026-08-04)


### Features

* hide search for small api enum selects ([b0b7bc5](https://github.com/bambamboole/spectacular/commit/b0b7bc5a822e305e10af34ff5a32e246fef15e34))
* refine api reference layout ([919e4e1](https://github.com/bambamboole/spectacular/commit/919e4e184316b7a9cf2bbaa475ce0cd10d3ae0b4))

## [0.3.0](https://github.com/bambamboole/spectacular/compare/0.2.1...0.3.0) (2026-08-03)


### Features

* add API layout workbench pages ([f5c44b8](https://github.com/bambamboole/spectacular/commit/f5c44b82c7d4972c96d3b6ff4e6989aba9113ee5))
* add API reference request playground ([6422bbd](https://github.com/bambamboole/spectacular/commit/6422bbda48cef7e55ca77af3bc6b517a9b5e506d))
* add asyncapi webhook attributes ([0643969](https://github.com/bambamboole/spectacular/commit/06439697602048ed7247c95be54da009cf3bd845))
* add copy-to-clipboard for the operation URL ([15b4e3b](https://github.com/bambamboole/spectacular/commit/15b4e3b220efa0552dbb5a5c213670160d2d2109))
* add grouped stacked API layout ([9275ad8](https://github.com/bambamboole/spectacular/commit/9275ad84b3401b518eced7025fa6e52e961d10e7))
* add interactive API request playground ([424a9f6](https://github.com/bambamboole/spectacular/commit/424a9f6b867da8c133155ce651a5ef4b441fcb98))
* add webhook asyncapi configuration ([f36ba3f](https://github.com/bambamboole/spectacular/commit/f36ba3fc884bcb72ede513c6aa6ce07f84a4343c))
* add webhook dispatch contracts ([45ed636](https://github.com/bambamboole/spectacular/commit/45ed6369e703e29df2f26e10032d72d80912bbb8))
* add webhook event registry ([80467a0](https://github.com/bambamboole/spectacular/commit/80467a019297330beaeca3c7cd4e04336461249c))
* adopt Lattice 0.37 primitives ([40cd056](https://github.com/bambamboole/spectacular/commit/40cd05690a79661f831d6cc9ff7b8550e1d54d32))
* adopt lattice 0.38 code blocks ([8111fae](https://github.com/bambamboole/spectacular/commit/8111fae847c8a0f9bb03bce3a246b4426948ac74))
* configure API playground bearer tokens ([8933cae](https://github.com/bambamboole/spectacular/commit/8933caec2f0bb56e095272d3e1e58fd68b7ee8ce))
* copy operations as Markdown ([cbe9bbb](https://github.com/bambamboole/spectacular/commit/cbe9bbb62d97629e5bca9017f99bcbb9cc5ea2a5))
* default try-out requests to JSON ([5d18a95](https://github.com/bambamboole/spectacular/commit/5d18a957a2401c4b3afc0295368c10a544459ba7))
* dispatch webhook events through spatie ([8cae892](https://github.com/bambamboole/spectacular/commit/8cae892ca7e78902743073d2806faf243e94f203))
* document Laravel webhook events in AsyncAPI ([024b4e5](https://github.com/bambamboole/spectacular/commit/024b4e5834bef8f22987144a37b7e5feb0100c73))
* document webhooks and broadcast notifications in AsyncAPI ([6ba0fdc](https://github.com/bambamboole/spectacular/commit/6ba0fdc73ef7994b46af4819545be5ff4b83ef79))
* document X-Total-Count response header on categories endpoint ([fba3266](https://github.com/bambamboole/spectacular/commit/fba3266ece2db31150ad7066893748eb1a2afa70))
* edit JSON request bodies with fields ([237f023](https://github.com/bambamboole/spectacular/commit/237f023b51853d6ce248025581fe8754bff1cded))
* edit request parameters inline ([91d34ad](https://github.com/bambamboole/spectacular/commit/91d34adb79ac9b28ed5a03a57da8ee88de5b2f68))
* execute API requests in the browser ([f874eeb](https://github.com/bambamboole/spectacular/commit/f874eeba7b88bca659b0501f1448f7b6bd70a9b3))
* expose executable request metadata ([ad0b2d2](https://github.com/bambamboole/spectacular/commit/ad0b2d28d14baf0bca27b3e0eb9edd84829aa851))
* extend OpenAPI viewer metadata ([aac7eaa](https://github.com/bambamboole/spectacular/commit/aac7eaab2f148c4b3ab0b124735028379ce8536a))
* extend OpenAPI viewer metadata ([fae2479](https://github.com/bambamboole/spectacular/commit/fae2479342b1513397d4b70d9c5308e7011cf2a0))
* generate asyncapi webhook messages ([f87cf61](https://github.com/bambamboole/spectacular/commit/f87cf61ea183f19871551395de1779784ee13b0e))
* generate executable request snippets ([d2ac89a](https://github.com/bambamboole/spectacular/commit/d2ac89a36efd28d289115e6ab1e204bb50e40b6a))
* generate minimal request examples ([59db35e](https://github.com/bambamboole/spectacular/commit/59db35e953dd655becbbd9b7f16dcffd592e6c80))
* generate response examples from schemas ([b111cc9](https://github.com/bambamboole/spectacular/commit/b111cc9d09937a7139f0fa5ea190081a19e5bcb7))
* improve API code blocks ([50438d0](https://github.com/bambamboole/spectacular/commit/50438d0b3d5330eac8eeeae72fa9852338bb3a60))
* improve OpenAPI request playground ([026cf16](https://github.com/bambamboole/spectacular/commit/026cf16274b13c1d7e4e3ab836ef19d20379e260))
* improve OpenAPI request playground ([51b33f4](https://github.com/bambamboole/spectacular/commit/51b33f4477d5943c046c0d683b54bbc12f57f648))
* infer webhook and notification payload schemas ([dc7bebd](https://github.com/bambamboole/spectacular/commit/dc7bebd7c6e33c55dcb835895d05da932a2197e3))
* paginate workbench categories ([7a72452](https://github.com/bambamboole/spectacular/commit/7a72452384b0e815bb147e6e04d0ffbde95a2f73))
* parse response headers from the OpenAPI document ([ef5af88](https://github.com/bambamboole/spectacular/commit/ef5af881c05139a9c1dfc6d9ee70d3b82c4ed8a6))
* render response headers in the API reference viewer ([07f5e2a](https://github.com/bambamboole/spectacular/commit/07f5e2a5d3bb50907dde5888ce16f7c677d358db))
* seed the workbench API ([23d4e00](https://github.com/bambamboole/spectacular/commit/23d4e003ad18311b7e9196d223b9fbaca1f832c3))
* use Lattice Badge for method pills and deprecated markers ([9c66194](https://github.com/bambamboole/spectacular/commit/9c66194553b5ac20fdda265a13cb5a6e784fbf17))
* use Lattice Icon for the schema tree expand/collapse caret ([6f64495](https://github.com/bambamboole/spectacular/commit/6f6449517bdbdeffef59c64cf1578c8198042359))
* use Lattice Input and NativeSelect in the nav sidebar ([1df1264](https://github.com/bambamboole/spectacular/commit/1df12641f4a23a33602357db70a1d2088246f9c1))
* use Lattice SegmentedPills for the response and schema/example tabs ([ad0052b](https://github.com/bambamboole/spectacular/commit/ad0052b3979dffbe9fb9a21583f65cda55345658))


### Bug Fixes

* align API reference browser contracts ([2da08e2](https://github.com/bambamboole/spectacular/commit/2da08e2c4b300a73d8a4f6add38537e7bb2ce887))
* align browser test dependency ranges ([af27532](https://github.com/bambamboole/spectacular/commit/af275322c497d45468c82e5b0a37939715f92926))
* align url copy with operation url ([3e4be6d](https://github.com/bambamboole/spectacular/commit/3e4be6d14a34dc8139ff4487600e6344bcbcf34b))
* allow Vitest in CI ([3a827b9](https://github.com/bambamboole/spectacular/commit/3a827b91e56fa129e73bf31e9dc35651e66dace6))
* cache webhook lookups in registry ([2af665f](https://github.com/bambamboole/spectacular/commit/2af665f1ab346ceb69e091a53805288bd8c2ac09))
* clamp API request durations ([478a929](https://github.com/bambamboole/spectacular/commit/478a929ae96ee9a40dd61c8244aea5c8a9b29da3))
* convert remaining hand-rolled select and polish viewer components ([ca3dd36](https://github.com/bambamboole/spectacular/commit/ca3dd368313e8b09f1fce400d055a25f5eb08968))
* document parameter enum values ([d7212c3](https://github.com/bambamboole/spectacular/commit/d7212c3545f8446549134128050ded9a7de4be60))
* emit webhook asyncapi headers ([a5ffbb2](https://github.com/bambamboole/spectacular/commit/a5ffbb28aa4e14aa6251c719d8763379a99401ee))
* harden API reference request handling ([b01a0dc](https://github.com/bambamboole/spectacular/commit/b01a0dca285ba0dabb7261d7363b06c3e6254ad8))
* harden API request playground ([343e5c8](https://github.com/bambamboole/spectacular/commit/343e5c8009f941eaeb1411705060cd1506e6d328))
* harden asyncapi payload schema inference ([11da9b3](https://github.com/bambamboole/spectacular/commit/11da9b35531e8d97841da44981ed8713100ea2e4))
* harden executable request construction ([6204bd7](https://github.com/bambamboole/spectacular/commit/6204bd7461c27b733ad437cbba1cf9c1830f807f))
* harden webhook event dispatching ([dd898a6](https://github.com/bambamboole/spectacular/commit/dd898a66e58a504c48f49ff292c65d81ac12eb9e))
* honor asyncapi generation overrides ([ee600ce](https://github.com/bambamboole/spectacular/commit/ee600ce197af2521404c5db2aabba603abfe3fc8))
* isolate request editor state ([9e57f8b](https://github.com/bambamboole/spectacular/commit/9e57f8b81b4592d4a965556cfec7d46ecd18f873))
* keep webhook registry discovery recursive ([08f9ddc](https://github.com/bambamboole/spectacular/commit/08f9ddc9cc61bfc46064db0a703db9a0400b3ddd))
* merge duplicate npm package installation blocks in README ([369e6fd](https://github.com/bambamboole/spectacular/commit/369e6fda8483f22de9ff34dc403561028cd22a34))
* normalize recursive Markdown definitions ([b376343](https://github.com/bambamboole/spectacular/commit/b376343d574219bb28144e6364007e90826cc078))
* omit empty unsupported request parameters ([bf152f1](https://github.com/bambamboole/spectacular/commit/bf152f19dda7cf012af171598ac5e06ccbda534b))
* preserve webhook asyncapi defaults ([46a2860](https://github.com/bambamboole/spectacular/commit/46a2860deab79e007bab7578b32b128458869501))
* recognize specialized asyncapi attributes ([d775287](https://github.com/bambamboole/spectacular/commit/d7752873d9748d6f2efe3ed5defd51ca0d21b400))
* reject AsyncAPI identifier collisions ([35a4237](https://github.com/bambamboole/spectacular/commit/35a4237fde8c5f08cfe4b88f1e5d44fedd6b212e))
* repair API reference ref resolution, adopt Lattice UI primitives ([0769929](https://github.com/bambamboole/spectacular/commit/07699299786fd6b05eb8b42f4717845b76259dba))
* repair workbench API try-out ([57d4cf2](https://github.com/bambamboole/spectacular/commit/57d4cf21e4b6f4f2cc194039bf8e738d44858d36))
* restore lattice echo dependency ([38d2f07](https://github.com/bambamboole/spectacular/commit/38d2f070b201a8f24269923f2484144d996cb4b5))
* ship the Buffer polyfill from the real plugin entry point ([364d250](https://github.com/bambamboole/spectacular/commit/364d250ae3986f5c3920aef60eca77a6e332229e))
* store workbench user passwords ([452968c](https://github.com/bambamboole/spectacular/commit/452968c003344ca4991777c358d1de698ace6b83))
* tighten webhook dispatch contracts ([f06de80](https://github.com/bambamboole/spectacular/commit/f06de80979e2117940c36ff2b922e09dcbf73463))
* unconditional useState in ResponsesSection to satisfy Rules of Hooks ([4db7e92](https://github.com/bambamboole/spectacular/commit/4db7e92989cca0beb0a5aa558d404659a382ad7a))
* use same-origin API in workbench ([f225a62](https://github.com/bambamboole/spectacular/commit/f225a62b31648f6ac4a1dd0885e0af879c250492))
* use select for response types ([b44b5d5](https://github.com/bambamboole/spectacular/commit/b44b5d5a46f4608d002e21da100b4eff8cc9259b))
* widen and simplify API request playground ([4822024](https://github.com/bambamboole/spectacular/commit/4822024b88d1c275e23ecb982472f900f91ff86d))
* wire up the Lattice SVG sprite so viewer icons actually render ([54a64d5](https://github.com/bambamboole/spectacular/commit/54a64d5fd4e7c6a6ed8cb84a98ff17184c5414db))


### Performance Improvements

* cache class discovery per scan path set ([edc4db6](https://github.com/bambamboole/spectacular/commit/edc4db63966bc9adf63ce7f8b3e9f883c0020503))

## [0.2.1](https://github.com/bambamboole/spectacular/compare/0.2.0...0.2.1) (2026-08-02)


### Bug Fixes

* declare the PHP discovery root so component types reach consuming apps ([0dbf258](https://github.com/bambamboole/spectacular/commit/0dbf2589b471f6a01740e0b11927440bb6dd4068))
* register the Lattice plugin for auto-discovery and lazy-load the viewer ([a312414](https://github.com/bambamboole/spectacular/commit/a3124141da7f744f6aa43e1d632b8fde8dbd5329))
* register the Lattice plugin for auto-discovery and lazy-load the viewer ([5cc4ac2](https://github.com/bambamboole/spectacular/commit/5cc4ac2bbc975436dcf0808801d70bb2105341ab))

## [0.2.0](https://github.com/bambamboole/spectacular/compare/0.1.0...0.2.0) (2026-08-01)


### Features

* adapt openapi request bodies into the ApiDocument model ([97cfa39](https://github.com/bambamboole/spectacular/commit/97cfa391361987a556b687eec449138730cc3534))
* add a POST /users endpoint with a request body to the workbench ([16cf3d4](https://github.com/bambamboole/spectacular/commit/16cf3d4b32aa8689bf17dd4316b27dee622149e2))
* add a self-referential category resource to the workbench ([5b5d2d7](https://github.com/bambamboole/spectacular/commit/5b5d2d7c467d59e37dc41c8f90669bb1627ffcf0))
* add cycle-safe json-schema to rows transform ([62927b6](https://github.com/bambamboole/spectacular/commit/62927b6410571d978019959667f0dceaf928418b))
* add layout and scoping modifiers to the api-reference builder ([eeb0d86](https://github.com/bambamboole/spectacular/commit/eeb0d86cbdd2f25691ac3da9f0982851c467abae))
* add OpenApiAdapter mapping paths to the ApiDocument model ([c6911a3](https://github.com/bambamboole/spectacular/commit/c6911a3711820deb77ba15866d4a9ab4918c2853))
* add spectacular.api-reference lattice component + builder ([8911cd9](https://github.com/bambamboole/spectacular/commit/8911cd9419cc00cfd9e2fd07e335dc0a08623b31))
* add spectacular.schema-tree lattice component ([f796a53](https://github.com/bambamboole/spectacular/commit/f796a53585ff05ac33e9703879699812815d7f84))
* add the format-agnostic ApiDocument model ([78be6cd](https://github.com/bambamboole/spectacular/commit/78be6cde294f54573aca192eea87433a2e173c18))
* add users show endpoint to workbench ([5155408](https://github.com/bambamboole/spectacular/commit/5155408b36dc8a1c31c7a9809d2ddd17d7c556eb))
* API header + request bodies in the OpenAPI viewer ([8d8d21e](https://github.com/bambamboole/spectacular/commit/8d8d21e2028aac42d043abe82cb298c71377d672))
* client viewer cleanup + security/servers, examples, and builder modifiers ([665270d](https://github.com/bambamboole/spectacular/commit/665270d07c7f398d7b03bcaecd93963946392561))
* client-side api-reference UI (nav + lazy operation view) ([fa502d8](https://github.com/bambamboole/spectacular/commit/fa502d8df8309073700b979c76872a6821d92d10))
* client-side lazy API-reference viewer (scales to large specs) ([fbf641f](https://github.com/bambamboole/spectacular/commit/fbf641f7237b3c3b9b5c0b1df056049e6ea4af51))
* client-side OpenAPI navigation + lazy operation parser ([e0aa2b2](https://github.com/bambamboole/spectacular/commit/e0aa2b2ab865838de8bce38eca7f2276cc069d2f))
* compile the ApiDocument into a lattice nav + operation shell ([9ddf9e4](https://github.com/bambamboole/spectacular/commit/9ddf9e4c93de167c661fc074bdc3d8612fe40e4a))
* OpenAPI viewer Phase 0 — Lattice schema-tree spike ([ae345a8](https://github.com/bambamboole/spectacular/commit/ae345a8ae2cb469238fc1507bbb6b7d93fbc797a))
* render a lattice page in the workbench dev harness ([7efca99](https://github.com/bambamboole/spectacular/commit/7efca99de075c85d4f4683725ce1e5739153f906))
* render request and response examples in the client viewer ([4a9beb7](https://github.com/bambamboole/spectacular/commit/4a9beb7a10fb97a08cd051baea9c63a60b448f2e))
* render the API info header above the openapi viewer ([c707454](https://github.com/bambamboole/spectacular/commit/c707454c9b2b3abc7d0491e93c9e59a553e3c343))
* render the client api-reference in the workbench ([5713bfa](https://github.com/bambamboole/spectacular/commit/5713bfa0a596a868accaffdcf8da1002d7b9a8c4))
* render the openapi endpoints as nav + operation shells in the workbench ([5d3b8d1](https://github.com/bambamboole/spectacular/commit/5d3b8d11740daec2dc0ec702aca5b8ad0c833628))
* render the recursive category schema in the workbench viewer ([77f487b](https://github.com/bambamboole/spectacular/commit/77f487beab59ec11aa22cc4e26cc997dc5adbdff))
* render the request body section in the openapi viewer ([6e144e2](https://github.com/bambamboole/spectacular/commit/6e144e27e70d97f1fe2da18129b71b6f7c90bc38))
* surface security requirements and servers in the client viewer ([d4da2af](https://github.com/bambamboole/spectacular/commit/d4da2af3d58b9a900c83ba74b4625cb1af86d364))


### Bug Fixes

* **doc:** emit scroll-target anchors for operation nav links ([a92d51a](https://github.com/bambamboole/spectacular/commit/a92d51a996036abbf2304930059a498ea994bb2e))
* **openapi:** add multi-tagged operations to every tag group ([2358c54](https://github.com/bambamboole/spectacular/commit/2358c54976cc6d5735859edf6a8ef9277f95a774))
* polyfill Buffer for browser-side schema $ref resolution ([f82b6e2](https://github.com/bambamboole/spectacular/commit/f82b6e20395d2ef947d29ced5b5ac21e464c403d))
* regenerate OpenAPI fixture for corrected scramble type inference ([225e08b](https://github.com/bambamboole/spectacular/commit/225e08bcb75918cd4691837e9105cddd85ae5888))
* reset OperationView state when selected operation changes ([d1888a7](https://github.com/bambamboole/spectacular/commit/d1888a7ee1386698279ecd36f0556153a43cf984))
* resolve response $ref components and show descriptions in viewer ([6d906ae](https://github.com/bambamboole/spectacular/commit/6d906ae014c966006d4b5872aae434e2c6ae3bac))
* type the globalThis.Buffer polyfill assignment ([ff74027](https://github.com/bambamboole/spectacular/commit/ff74027196cb269291703c5d473c94b95206ebf7))

## 0.1.0 (2026-07-03)


### Features

* support a title on AsyncAPI messages ([d4fdcac](https://github.com/bambamboole/spectacular/commit/d4fdcace9c2aca2b1a3467d9cd786c78d82c1d01))


### Miscellaneous Chores

* release 0.1.0 ([2bf49d4](https://github.com/bambamboole/spectacular/commit/2bf49d41e06fca661a1e52f587b71baa1e72a35c))
