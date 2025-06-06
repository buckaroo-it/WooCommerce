<?php

namespace Buckaroo\Woocommerce\Install\Migration;

/**
 * Interface to extenda version migration
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 2.25.0
 *
 * @link      https://www.buckaroo.eu/
 */
interface Migration
{
    /**
     * Execute migration,
     * each version should attempt to be backward compatible with previous versions
     * if the users decides to downgrade
     *
     * @return void
     */
    public function execute();
}
