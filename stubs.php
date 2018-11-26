<?php

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\Store\EntityTitleLookup;

class SiteList {}
function wfTimestamp( $format, $timestamp ) {
	return gmdate( $format, $timestamp );
}
define( 'TS_ISO_8601', 'Y-m-d\TH:i:s\Z' );

class MyPropertyDataTypeLookup implements PropertyDataTypeLookup {
	public function getDataTypeIdForProperty( PropertyId $propertyId ) {
		return 'string';
	}
}

class MyEntityTitleLookup implements EntityTitleLookup {
	public function getTitleForId( EntityId $id ) {
		return null;
	}
}
