<?php

/*
  The RP-initiated logout URL (OpenID Connect RP-Initiated Logout 1.0).

  Providers such as Keycloak refuse a logout request that does not identify
  the session being ended: without id_token_hint they show an error page
  ("Missing parameters: id_token_hint") instead of logging the user out
  (#891). client_id is sent as well, which is what providers accept in place
  of the hint when the ID token is no longer known.
*/

/**
 * @param string      $logoutUrl   The provider's end-session endpoint.
 * @param string      $returnTo    post_logout_redirect_uri, or '' for none.
 * @param string|null $idToken     The ID token issued at login, if known.
 * @param string|null $clientId    The client id Wallos is registered with.
 * @return string
 */
function oidc_build_logout_url($logoutUrl, $returnTo, $idToken = null, $clientId = null)
{
    $params = [];

    if (is_string($idToken) && $idToken !== '') {
        $params['id_token_hint'] = $idToken;
    }

    if (is_string($clientId) && $clientId !== '') {
        $params['client_id'] = $clientId;
    }

    if (is_string($returnTo) && $returnTo !== '') {
        $params['post_logout_redirect_uri'] = $returnTo;
    }

    if (empty($params)) {
        return $logoutUrl;
    }

    $separator = strpos($logoutUrl, '?') === false ? '?' : '&';

    return $logoutUrl . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
