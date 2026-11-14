<?php

declare(strict_types=1);

namespace Four\Http\Authentication;

/**
 * How an OAuth 2.0 client authenticates at the token endpoint (RFC 6749 §2.3.1).
 */
enum ClientAuthMethod: string
{
    /** client_id and client_secret as form fields in the request body */
    case ClientSecretPost = 'client_secret_post';

    /** client_id and client_secret as HTTP Basic credentials, not in the body */
    case ClientSecretBasic = 'client_secret_basic';
}
