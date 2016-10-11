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

	public function __construct () {

		parent::__construct();
		$this->mDescription = "Count the recent hits for each page.";

		$DIR = "/tmp";
		$this->fileDisambig = "$DIR/disambig.mediawiki";
		$this->fileXml = "$DIR/mwTransfer.xml";
		$this->fileMove = "$DIR/mwMovePage.txt";


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

		$this->getPages()
		// $this->doImport();

	}

	public function importUniquePage ($pagename, $wiki) {

		unlink( $this->fileXml );
		shell_exec( "WIKI=$wiki php dumpBackup.php \"$pagename\" > {$this->$fileXml}" );
		shell_exec( "WIKI={$this->mergedwiki} php importDump.php < {$this->$fileXml}" );

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
			shell_exec( "WIKI=$wiki php dumpBackup.php \"$pagename\" > $fileXml" );
			shell_exec( "WIKI=$mergedwiki php importDump.php < $fileXml" );
			shell_exec( "WIKI=$mergedwiki php moveBatch.php --noredirects -r \"$deconflictMsg\" $fileMove" );
			$disambigForTemplate .= "$pagename ($wikiForTitle)\n";
		}

		$disambigForTemplate .= "}}";
		file_put_contents( "$fileDisambig", $disambigForTemplate );
		shell_exec( "WIKI=$mergedwiki php edit.php -s \"$disambigMsg\" \"$pagename\" < $fileDisambig" );

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
				COUNT( distinct texthash ) AS uniques,
				GROUP_CONCAT(wiki) AS wikis,
			FROM (
				$union
			) AS tmp
			GROUP BY page_namespace, page_title";

		echo $query;

		$dbr = wfGetDB( DB_SLAVE );

		$result = $dbr->query( $query );
		while( $page = $result->fetchObject() ) {
			$this->handleImport( $page );
		}

		return;

	}

	public function handleImport ( $page ) {

		$wikis = explode( ",", $page->wikis );
		$pageTitleObj = Title::makeTitle( $page->page_namespace, $page->page_title );
		$pageTitleText = $pageTitleObj->getFullText();

		if ( count( $wikis ) === 1 ) {
			$this->importUniquePage( $pageTitleText, $wikis[0] );
		}
		else if ( $page->uniques === 1 ) {
			$this->importIdenticalPages( $pageTitleText, $wikis );
		}
		else {
			$this->importConflictedPages( $pageTitleText, $wikis );
		}

	}

}

$maintClass = "UniteTheWikis";
require_once( DO_MAINTENANCE );
