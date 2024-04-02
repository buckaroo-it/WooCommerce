<?php

/**
 * This file is part of Gitonomy.
 *
 * (c) Alexandre Salomé <alexandre.salome@gmail.com>
 * (c) Julien DIDIER <genzo.wm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Buckaroo\Dependencies\Gitonomy\Git;

class CommitReference
{
    /**
     * @var string
     */
    private $hash;

    public function __construct($hash)
    {
        $this->hash = $hash;
    }
}
