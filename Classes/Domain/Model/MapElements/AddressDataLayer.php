<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

class AddressDataLayer extends Layer {



    public function getJavaScript(): string
    {

        return 'const '.$this->getUniqueIdentifier().' = L.tileLayer(\'https://tile.openstreetmap.org/{z}/{x}/{y}.png\', {
		maxZoom: 19
	    }).addTo('.$this->getRootMap()->getIdentifier().');';
    }



}
