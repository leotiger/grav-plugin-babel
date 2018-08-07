<?php
namespace Grav\Plugin\Babel;

class BabelResultObject
{
    protected $items;
    protected $counter;

    public function __construct($items)
    {
        $this->counter = 0;
        $this->items   = $items;
    }
    public function fetch($options)
    {
        return $this->items[$this->counter++];
    }
}