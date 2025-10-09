# OpenID Connect integration for TYPO3 - changelog

## Version 4.0.1

- Fix redirect_url handling for Login-Plugin

## Version 4.0.0

- Breaking: Existing fe_users are not looked up by their username anymore.
  You may use the `AuthenticationFetchUserEvent` to re-add this functionality,
  if this is secure for your use case.
  See commit `[!!!][SECURITY] Do not look up existing users via username field` for details.
- Breaking: Upon login the user's username and email address will now be updated
  according to the mapping configuration. The default mapping configuration maps
  the username, but not the email address. Custom mapping configurations can now
  map none, one or both of those fields.
  It is now possible to post-process the mapping by ìmplementing the `AuthenticationProcessMappingEvent`
- The query parameters for the authorization URL can now be modified via `GetAuthorizationUrlEvent`.

## Version 3.0.0

- The callback URL changed from `/typo3conf/ext/oidc/Public/callback.php` to `TYPO3_SITE_URL`. (configurable with option `oidcRedirectUri`) [#116](https://github.com/xperseguers/t3ext-oidc/issues/116)
- No PHP native session is needed anymore. A JWT-Cookie (named `oidc_context`) is now used to store relevant information during an authentication process. [#155](https://github.com/xperseguers/t3ext-oidc/issues/155)
- A dedicated route is used to initiate the authorization flow with the identity provider. (configurable with option `authenticationUrlRoute`)
  This avoids creating loads of authentication sessions with the identity provider (IdP), if the Login-button
  is placed on a Login-page for instance. Formerly a new auth-session was started with the IdP
  every time the page was rendered. [#159](https://github.com/xperseguers/t3ext-oidc/issues/159)
- All previous hooks have been replaced with PSR-14 events. More events were added.
- The extension is now wiring the underlying OAuth2 library with TYPO3's Guzzle wrapper (`GuzzleClientFactory`).
  This means that requests done by the library now adhere to TYPO3 configuration. [#167](https://github.com/xperseguers/t3ext-oidc/issues/167)
- Added an event allowing to adjust the where-conditions for fetching the existing fe_users [#164](https://github.com/xperseguers/t3ext-oidc/issues/164)
- Enhanced events to include a reference to the AuthenticationService [#136](https://github.com/xperseguers/t3ext-oidc/issues/136)
- Added a user groups event to map groups by a different pattern than "Roles", e.g. "claims" [#129](https://github.com/xperseguers/t3ext-oidc/pull/129)
