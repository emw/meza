<?php
/**
 * This script merges the contents of several wikis into one (blank) wiki
 *
 * It does this by:
 *
 *   (1) Get all pages on all merging wikis
 *       - If same name, not same content:
 *          - Import pages as [[Original page name (WIKI_ID_CAPITALIZED)]]
 *          - Create page [[Original page name]] as disambiguation page
 *            with SMW data pointing to pages for header/footer use
 *       - If same name, same content: For now just pick one and import it
 *       - If not same name: import it normally
 *
 *   (2) NOT IMPLEMENTED: Ideally templates, forms, properties, and categories
 *       would be handled differently. Something like this:
 *
 *       - Templates: For all pages (templates and otherwise) that link to the
 *         template do: \{\{\s*template-name  --> {{template-name (wikiid)
 *
 *       - Forms: Change "for template" to point to new name of templates.
 *         Could be many different templates within an form, and it'll be hard
 *         to bookkeep what templates have new names. Hmm...
 *
 *       - Properties: As long as they have the same type it doesn't really
 *         matter that much. Perhaps check for type, then if same just take
 *         the longest page.
 *
 *       - Categories: Change "Has form" to point to "Form name (new-wiki-id)"
 *         For all pages in the category, as well as the similarly named
 *         template for the category, do find/replace:
 *         \[\[Category:Category name\]\]  -->  [[Category:Category name (wikiId)]]
 *
 *   (3) NOT IMPLEMENTED: Import files to the new wiki
 *
 * Usage:
 *  mergedwiki wiki ID to merge into
 *  sourcewikis: comma-separated list of wiki IDs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @author James Montalvo
 * @ingroup Maintenance
 */
require_once( '/opt/meza/htdocs/mediawiki/maintenance/Maintenance.php' );
class UniteTheWikis extends Maintenance {

	protected $mergedwiki;
	protected $sourcewikis;
	protected $fileDisambig;
	protected $fileXml;
	protected $fileMove;
	protected $pages;
	protected $db;
	protected $maintDir = '/opt/meza/htdocs/mediawiki/maintenance/';

	public function __construct () {

		parent::__construct();
		$this->mDescription = "Count the recent hits for each page.";

		$DIR = "/tmp";
		$this->fileDisambig = "$DIR/disambig.mediawiki";
		$this->fileXml = "$DIR/mwTransfer.xml";
		$this->fileMove = "$DIR/mwMovePage.txt";
		$this->fileDumpList = "$DIR/mwDumpList.txt";


		// addOption ($name, $description, $required=false, $withArg=false, $shortName=false)
		$this->addOption(
			'mergedwiki',
			'Which wiki will all the pages be merged into',
			true, true );

		$this->addOption(
			'sourcewikis',
			'Comma separated list of wikis to pull from',
			true, true );

	}

	public function execute () {

		$this->mergedwiki = $this->getOption( 'mergedwiki' );
		$this->sourcewikis = explode( ',', $this->getOption( 'sourcewikis' ) );

		$this->getPages();

	}

	public function dumpPageXML ($pages, $wiki) {
		unlink( $this->fileDumpList );

		if ( is_array($pages) ) {
			$pagelist = implode( "\n", $pages );
		}
		else {
			$pagelist = $pages;
		}
		file_put_contents( $this->fileDumpList, $pagelist );
		shell_exec( "WIKI=$wiki php {$this->maintDir}dumpBackup.php --full --pagelist={$this->fileDumpList} > {$this->fileXml}" );
	}

	public function importUniquePage ($pages, $wiki) {

		unlink( $this->fileXml );
		$this->dumpPageXML( $pages, $wiki );
		shell_exec( "WIKI={$this->mergedwiki} php {$this->maintDir}importDump.php < {$this->fileXml}" );

		return;

	}

	public function importIdenticalPages ($pagename, $wikis) {

		// FIXME
		// For now, don't try to be smart about importing revisions. Ideally this would either:
		//   1. Determine which wiki should be imported based on quantity/age of revisions
		//   2. Smartly merge (and perhaps delete) all revisions from all wikis
		// For now just grab any page, biasing towards the oldest wiki (eva) if available.
		if ( in_array( "eva", $wikis ) ) {
			$this->importUniquePage( $pagename, "eva" );
		}
		else {
			$this->importUniquePage( $pagename, $wikis[0] );
		}

	}

	public function importConflictedPages ($pagename, $wikis) {

		$mergedwiki = $this->mergedwiki;

		$fileDisambig = $this->fileDisambig;
		$fileXml = $this->fileXml;
		$fileMove = $this->fileMove;

		$conflictWikis = implode( ', ', $wikis );
		$deconflictMsg = "$conflictWikis all with same page. Deconflicting name $pagename.";
		$disambigMsg = "Generate disambiguation page for conflicting pages on wikis: $conflictWikis";
		$disambigForTemplate = "{{Disambig|\n";

		foreach( $wikis as $wiki ) {
			unlink( $fileXml );
			unlink( $fileMove );
			$wikiForTitle = strtoupper( $wiki );
			file_put_contents( $fileMove, "$pagename|$pagename ($wikiForTitle)" );
			$this->dumpPageXML( $pagename, $wiki );
			shell_exec( "WIKI=$mergedwiki php {$this->maintDir}importDump.php < $fileXml" );
			shell_exec( "WIKI=$mergedwiki php {$this->maintDir}moveBatch.php --noredirects -r \"$deconflictMsg\" $fileMove" );
			$disambigForTemplate .= "$pagename ($wikiForTitle)\n";
		}

		$disambigForTemplate .= "}}";
		file_put_contents( "$fileDisambig", $disambigForTemplate );
		shell_exec( "WIKI=$mergedwiki php {$this->maintDir}edit.php -s \"$disambigMsg\" \"$pagename\" < $fileDisambig" );

		return;

	}

	public function getPages () {

		foreach( $this->sourcewikis as $wiki ) {
			$sqlParts[] = "
				SELECT
					\"$wiki\" AS wiki,
					page_namespace,
					page_title,
					md5( old_text ) AS texthash
				FROM wiki_$wiki.page AS p
				LEFT JOIN wiki_$wiki.revision AS r ON (r.rev_id = p.page_latest)
				LEFT JOIN wiki_$wiki.text AS t ON (t.old_id = r.rev_text_id)
			";
		}

		$union = implode( "\nUNION ALL\n", $sqlParts );
		$query = "
			SELECT
				page_namespace,
				page_title,
				COUNT( * ) AS num_wikis,
				COUNT( distinct texthash ) AS uniques,
				GROUP_CONCAT(wiki) AS wikis
			FROM (
				$union
			) AS tmp
			GROUP BY page_namespace, page_title
			ORDER BY page_namespace DESC, wiki";

		// echo $query;

		$dbr = wfGetDB( DB_SLAVE );

		$this->output( "\nStarting import\n===============\n" );

		$result = $dbr->query( $query );
		$importQueue = array();


		while( $page = $result->fetchObject() ) {

			$this->output( "\nNext DB row. Wikis=" . $page->wikis
				. "; NS=" . $page->page_namespace
				. "; title=" . $page->page_title );

			// get the current wiki being put into the queue. If the queue has
			// pages in it, grab the `wikis` property of any page (they're all
			// the same). If the queue is empty, set the queue-wiki to the wiki
			// of the current loop
			$importQueueWiki = count($importQueue) > 0 ? $importQueue[0]->wikis : $page->wikis;

			if ( intval($page->num_wikis) ===  1 && $importQueueWiki === $page->wikis ) {
				$this->output( "\n  --> Queue" );
				$importQueue[] = $page;
			}
			else {
				// Import the pages in the queue and clear it
				if ( count( $importQueue ) > 0 ) {
					$this->output( "\n\nProcess queue..." );
					$this->handleImport( $importQueue );
					$importQueue = array();
				}

				// Do this import
				$this->output( "\nHandling import for page " . $page->page_title );
				$this->handleImport( $page );
			}
		}

		// if the while-loop ended with some in the queue, import them
		if ( $importQueue !== null ) {
			$this->handleImport( $importQueue );
		}

		return;

	}

	public function handleImport ( $pages ) {

		// Determine if this is multiple pages or just one
		if ( is_array($pages) && count($pages) > 1 ) {
			// if multiple pages, they'll all be from the same wiki
			$wikis = array( $pages[0]->wikis );
			$pageTitleText = array();
			$this->output( "\nImporting multiple pages from " . $pages[0]->wikis . ": ");
			foreach( $pages as $page ) {
				$pageTitleObj = Title::makeTitle( $page->page_namespace, $page->page_title );
				$text = $pageTitleObj->getFullText();
				$pageTitleText[] = $text;
				$this->output( "\n  * $text" );
			}
		}
		else {
			if ( is_array($pages) && count($pages) === 1 ) {
				$pages = $pages[0];
			}
			$wikis = explode( ",", $pages->wikis );
			$pageTitleObj = Title::makeTitle( $pages->page_namespace, $pages->page_title );
			$pageTitleText = $pageTitleObj->getFullText();
			$this->output( "\nImporting page $pageTitleText from " . $pages->wikis );
		}


		if ( count( $wikis ) === 1 ) {
			$this->output( "\n\nImport unique page(s)\n" );
			$this->importUniquePage( $pageTitleText, $wikis[0] );
		}
		else if ( $pages->uniques === 1 ) {
			$this->output( "\n\nImport identical pages\n" );
			$this->importIdenticalPages( $pageTitleText, $wikis );
		}
		else {
			$this->output( "\n\nImport conflicted pages\n" );
			$this->importConflictedPages( $pageTitleText, $wikis );
		}

	}

}

$maintClass = "UniteTheWikis";
require_once( DO_MAINTENANCE );
