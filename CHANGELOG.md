# Changelog

## [0.2.0](https://github.com/bambamboole/spectacular/compare/0.1.0...0.2.0) (2026-07-10)


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
* reset OperationView state when selected operation changes ([d1888a7](https://github.com/bambamboole/spectacular/commit/d1888a7ee1386698279ecd36f0556153a43cf984))
* resolve response $ref components and show descriptions in viewer ([6d906ae](https://github.com/bambamboole/spectacular/commit/6d906ae014c966006d4b5872aae434e2c6ae3bac))

## 0.1.0 (2026-07-03)


### Features

* support a title on AsyncAPI messages ([d4fdcac](https://github.com/bambamboole/spectacular/commit/d4fdcace9c2aca2b1a3467d9cd786c78d82c1d01))


### Miscellaneous Chores

* release 0.1.0 ([2bf49d4](https://github.com/bambamboole/spectacular/commit/2bf49d41e06fca661a1e52f587b71baa1e72a35c))
