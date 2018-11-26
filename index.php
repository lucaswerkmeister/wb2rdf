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
use DataValues\StringValue;
use Deserializers\DispatchingDeserializer;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Rdf\DedupeBag;
use Wikibase\Rdf\EntityMentionListener;
use Wikibase\Rdf\EntityRdfBuilderFactory;
use Wikibase\Rdf\NullDedupeBag;
use Wikibase\Rdf\RdfBuilder;
use Wikibase\Rdf\RdfProducer;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Rdf\Values\LiteralValueRdfBuilder;
use Wikibase\Rdf\ValueSnakRdfBuilderFactory;
use Wikimedia\Purtle\RdfWriter;
use Wikimedia\Purtle\TurtleRdfWriter;

/*
$baseDataTypes = require 'extensions/Wikibase/lib/WikibaseLib.datatypes.php';
$repoDataTypes = require 'extensions/Wikibase/repo/WikibaseRepo.datatypes.php';
$dataTypes = $baseDataTypes;
foreach ( $repoDataTypes as $type => $repoDefinition ) {
	$baseDefinition = isset( $baseDataTypes[$type] ) ? $baseDataTypes[$type] : [];
	$dataTypes[$type] = array_merge( $baseDefinition, $repoDefinition );
	// hack: strip away everything except the RDF building, because the other stuff might require a repo
	if ( array_key_exists( 'rdf-builder-factory-callback', $dataTypes[$type] ) ) {
		$dataTypes[$type] = [
			'rdf-builder-factory-callback' => $dataTypes[$type]['rdf-builder-factory-callback'],
		];
	} else {
		$dataTypes[$type] = [];
	}
}
*/
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
		}
	]
];
$dataTypeDefinitions = new DataTypeDefinitions( $dataTypes );

$baseEntityTypes = require 'extensions/Wikibase/lib/WikibaseLib.entitytypes.php';
$repoEntityTypes = []; // require 'extensions/Wikibase/repo/WikibaseRepo.entitytypes.php';
$entityTypes = array_merge_recursive( $baseEntityTypes, $repoEntityTypes );
$entityTypeDefinitions = new EntityTypeDefinitions( $entityTypes );

$vocabulary = new RdfVocabulary(
	[
		'' => 'http://localhost/',
	],
	'http://localhost/'
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
	new NullDedupeBag(),
	new MyEntityTitleLookup()
);

$deserializerFactory = new DeserializerFactory(
	new DataValueDeserializer( [
		'string' => StringValue::class,
	] ),
	new DispatchingEntityIdParser(
		$entityTypeDefinitions->getEntityIdBuilders()
	)
);
$entityDeserializer = new DispatchingDeserializer( array_map(
	function ( $callback ) use ( $deserializerFactory ) {
		return $callback( $deserializerFactory );
	},
	$entityTypeDefinitions->getDeserializerFactoryCallbacks()
) );
$fullJson = file_get_contents( 'https://www.wikidata.org/wiki/Special:EntityData/Q5384579.json' );
$entity = $entityDeserializer->deserialize( json_decode( $fullJson, true )['entities']['Q5384579'] );

$builder->startDocument();
$builder->addEntity( $entity ) ;
$builder->finishDocument();

header( 'Content-Type: text/plain' ); // TODO change to turtle
echo $builder->getRDF();
