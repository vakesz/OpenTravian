<?php

declare(strict_types=1);

namespace Controller;

use Game\PublicHomepageLocale;
use Game\PublicRegistration;

/**
 * Registration lives in the `#registration` overlay on the public landing
 * page. This route only keeps the long-lived /register (and refer-a-friend
 * `?uc=`) URLs working by forwarding them there.
 */
class RegisterCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $this->redirect(PublicHomepageLocale::registrationUrl(null, PublicRegistration::referralCode()));
    }
}
