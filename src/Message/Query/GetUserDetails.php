<?php

namespace App\Message\Query;

class GetUserDetails implements QueryInterface
{
    public function __construct(
        public string $id
    )
    {
    }
}