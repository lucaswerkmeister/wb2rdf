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

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Rdf\EntityRdfBuilderFactory;
use Wikibase\Rdf\NullDedupeBag;
use Wikibase\Rdf\RdfBuilder;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Rdf\ValueSnakRdfBuilderFactory;
use Wikimedia\Purtle\TurtleRdfWriter;

$vocabulary = new RdfVocabulary(
	[
		'' => 'http://localhost/',
	],
	'http://localhost/'
);
$builder = new RdfBuilder(
	new SiteList(),
	$vocabulary,
	new ValueSnakRdfBuilderFactory( [] ),
	new MyPropertyDataTypeLookup(),
	new EntityRdfBuilderFactory( [] ),
	0,
	new TurtleRdfWriter(),
	new NullDedupeBag(),
	new MyEntityTitleLookup()
);

$builder->startDocument();
$builder->addDumpHeader();
$builder->addEntity( new Item(
	new ItemId( 'Q1' ),
	new Fingerprint( new TermList( [ new Term( 'en', 'test' ) ] ) )
)) ;
$builder->finishDocument();

header( 'Content-Type: text/plain' ); // TODO change to turtle
echo $builder->getRDF();
