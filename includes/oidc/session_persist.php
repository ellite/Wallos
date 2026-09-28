<?php

/**
 * Writes the session to disk now, and reopens it.
 *
 * The OIDC callback consumes the state and then spends a second or two at the
 * provider's token endpoint. PHP writes the session at the end of the request,
 * and the sign-in calls session_regenerate_id(true) before that, which deletes
 * the file the consumption would have been written to.
 *
 * So a second request carrying the same callback - which is what a browser on
 * an unstable connection produces - waits on the session lock, is handed the
 * state as it stood before it was consumed, and redeems the same authorization
 * code again. An authorization code is single-use, so the provider refuses the
 * second attempt and the person is shown a failure for a login that had in fact
 * succeeded (#1239).
 *
 * @return bool whether the session was written
 */
function oidc_persist_session()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $id = session_id();

    session_write_close();

    // Same id, so the client keeps the session it already has. Reopened
    // because everything after this point still writes to it.
    session_id($id);
    session_start();

    return true;
}
