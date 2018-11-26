<?php

ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );

require_once( 'vendor/autoload.php' );

global $wgAutoloadClasses;
$wgAutoloadClasses = [];

require_once( 'extensions/Wikibase/lib/autoload.php' );
require_once( 'extensions/Wikibase/data-access/autoload.php' );
require_once( 'extensions/Wikibase/repo/autoload.php' );

spl_autoload_register( function ( $fqn ) {
	global $wgAutoloadClasses;
	require_once( $wgAutoloadClasses[$fqn] );
} );

require_once( 'stubs.php' );

use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\MonolingualTextValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use Deserializers\DispatchingDeserializer;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Rdf\DedupeBag;
use Wikibase\Rdf\EntityMentionListener;
use Wikibase\Rdf\EntityRdfBuilderFactory;
use Wikibase\Rdf\HashDedupeBag;
use Wikibase\Rdf\JulianDateTimeValueCleaner;
use Wikibase\Rdf\NullDedupeBag;
use Wikibase\Rdf\NullEntityRdfBuilder;
use Wikibase\Rdf\PropertyRdfBuilder;
use Wikibase\Rdf\RdfBuilder;
use Wikibase\Rdf\RdfProducer;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Rdf\Values\ComplexValueRdfHelper;
use Wikibase\Rdf\Values\EntityIdRdfBuilder;
use Wikibase\Rdf\Values\GlobeCoordinateRdfBuilder;
use Wikibase\Rdf\Values\LiteralValueRdfBuilder;
use Wikibase\Rdf\Values\MonolingualTextRdfBuilder;
use Wikibase\Rdf\Values\QuantityRdfBuilder;
use Wikibase\Rdf\Values\TimeRdfBuilder;
use Wikibase\Rdf\ValueSnakRdfBuilderFactory;
use Wikimedia\Purtle\RdfWriter;
use Wikimedia\Purtle\TurtleRdfWriter;

function makeComplexValueHelper( $flags, RdfVocabulary $vocab, RdfWriter $writer, DedupeBag $dedupe ) {
	if ( $flags & RdfProducer::PRODUCE_FULL_VALUES ) {
		return new ComplexValueRdfHelper( $vocab, $writer->sub(), $dedupe );
	} else {
		return null;
	}
}

$dataTypes = [
	'VT:string' => [
		'rdf-builder-factory-callback' => function (
			$flags,
			RdfVocabulary $vocab,
			RdfWriter $writer,
			EntityMentionListener $tracker,
			DedupeBag $dedupe
		) {
			return new LiteralValueRdfBuilder( null, null );
		},
	],
	'VT:monolingualtext' => [
		'rdf-builder-factory-callback' => function (
			$flags,
			RdfVocabulary $vocab,
			RdfWriter $writer,
			EntityMentionListener $tracker,
			DedupeBag $dedupe
		) {
			return new MonolingualTextRdfBuilder();
		},
	],
	'VT:globecoordinate' => [
		'rdf-builder-factory-callback' => function (
			$flags,
			RdfVocabulary $vocab,
			RdfWriter $writer,
			EntityMentionListener $tracker,
			DedupeBag $dedupe
		) {
			$complexValueHelper = makeComplexValueHelper( $flags, $vocab, $writer, $dedupe );
			return new GlobeCoordinateRdfBuilder( $complexValueHelper );
		},
	],
	'VT:quantity' => [
		'rdf-builder-factory-callback' => function (
			$flags,
			RdfVocabulary $vocab,
			RdfWriter $writer,
			EntityMentionListener $tracker,
			DedupeBag $dedupe
		) {
			$complexValueHelper = makeComplexValueHelper( $flags, $vocab, $writer, $dedupe );
			$unitConverter = null; // not supported for now
			return new QuantityRdfBuilder( $complexValueHelper, $unitConverter );
		},
	],
	'VT:time' => [
		'rdf-builder-factory-callback' => function (
			$flags,
			RdfVocabulary $vocab,
			RdfWriter $writer,
			EntityMentionListener $tracker,
			DedupeBag $dedupe
		) {
			$dateCleaner = new JulianDateTimeValueCleaner();
			$complexValueHelper = makeComplexValueHelper( $flags, $vocab, $writer, $dedupe );
			return new TimeRdfBuilder( $dateCleaner, $complexValueHelper );
		},
	],
	'VT:wikibase-entityid' => [
		'rdf-builder-factory-callback' => function (
			$flags,
			RdfVocabulary $vocab,
			RdfWriter $writer,
			EntityMentionListener $tracker,
			DedupeBag $dedupe
		) {
			return new EntityIdRdfBuilder( $vocab, $tracker );
		},
	],
];
$dataTypeDefinitions = new DataTypeDefinitions( $dataTypes );

$baseEntityTypes = require 'extensions/Wikibase/lib/WikibaseLib.entitytypes.php';
$repoEntityTypes = [ // based on extensions/Wikibase/repo/WikibaseRepo.entitytypes.php
	'item' => [
		'rdf-builder-factory-callback' => function(
			$flavorFlags,
			RdfVocabulary $vocabulary,
			RdfWriter $writer,
			$mentionedEntityTracker,
			$dedupe
		) {
			// we do not support sitelinks for now, so nothing to do
			return new NullEntityRdfBuilder();
		},
	],
	'property' => [
		'rdf-builder-factory-callback' => function(
			$flavorFlags,
			RdfVocabulary $vocabulary,
			RdfWriter $writer,
			$mentionedEntityTracker,
			$dedupe
		) {
			return new PropertyRdfBuilder( $vocabulary, $writer );
		},
	],
];
$entityTypes = array_merge_recursive( $baseEntityTypes, $repoEntityTypes );
$entityTypeDefinitions = new EntityTypeDefinitions( $entityTypes );

$vocabulary = new RdfVocabulary(
	[
		'' => 'http://localhost/',
	],
	'http://localhost/',
	[ // see https://meta.wikimedia.org/wiki/Special_language_codes
		'simple' => 'en-simple',
		'crh' => 'crh-Latn',
		'cbk-zam' => 'cbk-x-zam',
		'map-bms' => 'jv-x-bms',
		'nrm' => 'fr-x-nrm',
		'roa-tara' => 'it-x-tara',
		'de-formal' => 'de-x-formal',
		'es-formal' => 'es-x-formal',
		'hu-formal' => 'hu-x-formal',
		'nl-informal' => 'nl-x-informal',
	],
	$dataTypeDefinitions->getRdfTypeUris()
);
$builder = new RdfBuilder(
	new SiteList(),
	$vocabulary,
	new ValueSnakRdfBuilderFactory(
		$dataTypeDefinitions->getRdfBuilderFactoryCallbacks( DataTypeDefinitions::PREFIXED_MODE )
	),
	new MyPropertyDataTypeLookup(),
	new EntityRdfBuilderFactory(
		$entityTypeDefinitions->getRdfBuilderFactoryCallbacks()
	),
	RdfProducer::PRODUCE_ALL,
	new TurtleRdfWriter(),
	new HashDedupeBag(),
	new MyEntityTitleLookup()
);

$entityIdParser = new DispatchingEntityIdParser(
	$entityTypeDefinitions->getEntityIdBuilders()
);
$deserializerFactory = new DeserializerFactory(
	new DataValueDeserializer( [
		'string' => StringValue::class,
		'monolingualtext' => MonolingualTextValue::class,
		'globecoordinate' => GlobeCoordinateValue::class,
		'quantity' => QuantityValue::class,
		'time' => TimeValue::class,
		'wikibase-entityid' => function ( $value ) use ( $entityIdParser ) {
			return new EntityIdValue( $entityIdParser->parse( $value['id'] ) );
		},
	] ),
	$entityIdParser
);
$entityDeserializer = new DispatchingDeserializer( array_map(
	function ( $callback ) use ( $deserializerFactory ) {
		return $callback( $deserializerFactory );
	},
	$entityTypeDefinitions->getDeserializerFactoryCallbacks()
) );
$fullJson = file_get_contents( 'https://www.wikidata.org/wiki/Special:EntityData/P31.json' );
$entity = $entityDeserializer->deserialize( json_decode( $fullJson, true )['entities']['P31'] );

$builder->startDocument();
$builder->addEntity( $entity ) ;
$builder->finishDocument();

header( 'Content-Type: text/plain' ); // TODO change to turtle
echo $builder->getRDF();
