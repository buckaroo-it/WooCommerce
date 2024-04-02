<?php

class Buckaroo_KlarnaIn extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    public function get_action(): string
    {
        return 'payInInstallments';
    }
}
