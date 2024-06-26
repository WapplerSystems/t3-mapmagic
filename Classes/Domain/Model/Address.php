<?php

namespace WapplerSystems\Map\Domain\Model;

/**
 * Address
 */
class Address extends \FriendsOfTYPO3\TtAddress\Domain\Model\Address
{


    /**
     * leafletmapicon
     *
     * @var string
     */
    protected $leafletmapicon = '';

    /**
     * mapgeocode
     *
     * @var string
     */
    protected $mapgeocode = '';

    /**
     * Returns the leafletmapicon
     *
     * @return string $this->leafletmapicon
     */
    public function getLeafletmapicon()
    {
        return $this->leafletmapicon;
    }

    /**
     * Sets the leafletmapicon
     *
     * @param string $leafletmapicon
     * @return void
     */
    public function setLeafletmapicon($leafletmapicon)
    {
        $this->leafletmapicon = $leafletmapicon;
    }


    /**
     * Returns mapgeocode
     *
     * @return string $this->mapgeocode
     */
    public function getmapgeocode()
    {
        return $this->mapgeocode;
    }

    /**
     * Sets mapgeocode
     *
     * @param string $mapgeocode
     * @return void
     */
    public function setMapgeocode($mapgeocode)
    {
        $this->mapgeocode = $mapgeocode;
    }

}
