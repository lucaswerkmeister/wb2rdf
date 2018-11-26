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

use DataValues\StringValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
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

$builder->startDocument();
$builder->addDumpHeader();
$builder->addEntity( new Item(
	new ItemId( 'Q1' ),
	new Fingerprint( new TermList( [ new Term( 'en', 'test' ) ] ) ),
	null,
	new StatementList( [
		new Statement(
			new PropertyNoValueSnak( new PropertyId( 'P1' ) ),
			null,
			null,
			'Q1$8133f72e-32f1-4866-b609-64862f08cea1'
		),
		new Statement(
			new PropertyValueSnak(
				new PropertyId( 'P2' ),
				new StringValue( 'test' )
			),
			null,
			null,
			'Q1$b12a156e-7ab9-4e87-849f-d258564c99ed'
		),
	] )
)) ;
$builder->finishDocument();

header( 'Content-Type: text/plain' ); // TODO change to turtle
echo $builder->getRDF();
