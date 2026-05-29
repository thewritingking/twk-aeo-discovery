<?php
/**
 * Per-identifier setup instructions for the post-wizard report.
 *
 * Keyed by the same identifier keys the wizard's registry uses, so the report
 * surfaces instructions for exactly what the user flagged as "do not have yet."
 * Content is intentionally practical: what it is, why it matters for entity
 * authority, the steps to actually get one, and how to verify.
 *
 * @package TWK_AEO_Discovery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TWKD_Instructions
 */
class TWKD_Instructions {

	/**
	 * Get instructions for a single identifier.
	 *
	 * @param string $scope 'person' or 'org'.
	 * @param string $key   Identifier key (orcid, wikidata, linkedin, ...).
	 * @return array|null   ['title' => ..., 'body' => ...] or null if unknown.
	 */
	public static function get( $scope, $key ) {
		$all = ( 'org' === $scope ) ? self::org() : self::person();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Person/author identifiers.
	 *
	 * @return array
	 */
	private static function person() {
		return array(

			'orcid' => array(
				'title' => 'ORCID iD',
				'body'  => 'ORCID is a free, persistent digital identifier for researchers, authors, and contributors. It is a 16-digit number (e.g. 0000-0002-1825-0097) that resolves to a public profile at orcid.org. Because ORCID is the canonical author identifier across academic publishing, indexing services, and increasingly the open web, it is the single highest-value sameAs you can add for entity authority. Search engines and AI answer engines recognize it.

To get one: go to orcid.org and click "Sign in / Register," then "Register now." Use a professional email you control long-term, not a work address you may lose. Registration takes about a minute and you immediately receive your iD. Set your iD visibility to "Everyone" (the default is "Trusted Parties" — change it). Add the name variants you publish under, your employment and education history, and your works. The high-leverage step most people skip: under Account Settings, link your ORCID to Scopus, Crossref, and the publisher search services for any journal where you have published. These connections auto-import your works and reinforce the identifier across the bibliographic graph.

Time: about 30 minutes to register and populate; ongoing curation as you publish.

Use the full URL as your sameAs value: https://orcid.org/XXXX-XXXX-XXXX-XXXX (not just the digits). Verify by opening that URL in a private browser window — your public profile should load with your name and your iD displayed.',
			),

			'isni' => array(
				'title' => 'ISNI',
				'body'  => 'ISNI (International Standard Name Identifier, ISO 27729) is the library-world counterpart to ORCID. Where ORCID covers research contributors, ISNI covers all public identities — authors, performers, organizations, fictional characters. It is widely used by national libraries, OCLC, and rights-management systems. ORCID iDs are now automatically linked to ISNI, so if you have an ORCID you may already have an ISNI assigned to you in the background.

The honest order of operations: search isni.org for your name first. If you already have an ISNI from library cataloging or via ORCID, use that. If you do not, the assignment paths are: (1) automatic, via library cataloging — when a book of yours is cataloged at a national library or major catalog, an ISNI tends to get attached over time; (2) via Bowker (US authors only), which charges about $99 to register an ISNI directly tied to your Books in Print listing; (3) via an ISNI Registration Agency like OCLC for institutions.

For most independent authors, the practical answer is: get an ORCID first (it is free and faster) and check ISNI again in a year. For traditionally-published authors with Bowker-listed books, the $99 ISNI is a fast and durable win.

Use the format https://isni.org/isni/XXXXXXXXXXXXXXXX (16 digits, no spaces) as your sameAs value. Verify by loading that URL — the public ISNI record should display your name and known works.',
			),

			'wikidata' => array(
				'title' => 'Wikidata item',
				'body'  => 'Wikidata is the structured-data project that underpins Wikipedia and feeds the knowledge graphs Google, Bing, and AI systems read. Every entity has a Q-number (e.g. Q42). A confirmed Wikidata item with your identifiers attached is, in practical terms, the single strongest entity-authority signal you can hold — but it is also the hardest to get, because Wikidata enforces notability.

Notability for a person, in Wikidata terms, means there is verifiable, independent, reliable coverage of you — published books with reviews, news articles about you (not by you), academic citations, recognized awards, or roles that have their own coverage. Self-published bylines and your own marketing pages do not qualify. If you are honestly unsure, you are probably not over the bar yet — work the other identifiers first.

Process if you meet the bar: search wikidata.org for your name. Already there? Note the Q-number; that is your item. If not, the correct path is for someone else to create it, not you. Wikidata has a strict conflict-of-interest policy on creating your own item, and self-created items often get nominated for deletion. Options: ask a colleague familiar with Wikidata; post a polite request at the WikiProject covering your field; or hire a reputable Wikidata editor (real ones exist; avoid anyone promising guaranteed inclusion). The item should be marked "instance of: human (Q5)," with occupation, country of citizenship, date of birth, and — crucially — your ORCID, ISNI, and other identifiers attached as properties. Those identifier properties are how the item gets reconciled with you across systems.

Use the URL https://www.wikidata.org/wiki/QXXXXX as your sameAs value. Verify that the public page loads, lists your identifiers, and points at the right person.',
			),

			'google_scholar' => array(
				'title' => 'Google Scholar profile',
				'body'  => 'Google Scholar profiles aggregate your indexed academic publications and citation counts in a single public page. If you have published peer-reviewed work, this is a quick, free, high-credibility identifier. If your output is primarily trade books, journalism, or business writing, Scholar will not have much to attach to and other identifiers serve you better.

To set one up: go to scholar.google.com and sign in with a Google account (preferably one tied to an institutional email — Scholar will offer to verify it). Click "My profile" and fill in your name, affiliation, areas of interest, and your verified email at your institution. Scholar will then suggest publications it thinks are yours; review each one carefully and accept only your own work. Set the profile to public and turn on automatic article updates (so newly indexed papers appear) with email confirmation (so wrong-author errors do not creep in).

Time: about 30 minutes for the initial setup; periodic curation when papers are misattributed.

Use the profile URL https://scholar.google.com/citations?user=XXXXXXXX (your user parameter, from the URL when you view your own profile) as your sameAs value. Verify by opening it in a private window — your name, affiliation, and publications should be visible without login.',
			),

			'linkedin' => array(
				'title' => 'LinkedIn (personal)',
				'body'  => 'A public LinkedIn profile with a clean vanity URL is one of the easiest sameAs entries and is widely recognized as an authoritative identity confirmation. Most people already have one; the work is usually just configuration.

If you have a profile: log in, go to "View profile," click "Edit public profile & URL" (top-right on the desktop view), and set a vanity URL that matches your name (linkedin.com/in/firstname-lastname/). In the same panel, set your public profile visibility to "Public" and check that at least your name, headline, photo, and current role are visible without login. The fields you make public are the entity signal; anything hidden does not count.

If you do not have one: create the account at linkedin.com, complete name and current role, add at least a few sentences of bio, and then do the vanity URL and visibility steps above.

Time: 5 minutes if you have a profile already; 30 if you are setting one up from scratch.

Use the canonical URL https://www.linkedin.com/in/your-handle/ (with the trailing slash) as your sameAs value. Verify by loading it in a private browser window — your public profile should appear without a login wall.',
			),

			'muckrack' => array(
				'title' => 'Muck Rack profile',
				'body'  => 'Muck Rack is the standard journalist-and-author directory used by PR teams. Profiles are crawled, verified, and link your bylines into a single identity record. For working journalists, authors with media coverage, and consultants who get quoted in the press, it is a strong identity confirmation. For people with no published bylines, there is nothing to verify and other identifiers will serve you better.

Process: go to muckrack.com and search your name first — if you have any traceable byline history, a stub profile may already exist. Claim it by clicking "Is this you?" and following the verification flow, which typically asks you to confirm via an email at one of the publications you have written for, or via your Twitter/X account associated with those bylines. If no stub exists, create a profile from the home page; you will be asked to add your beats, the publications you have written for, and links to representative articles. Muck Rack staff verify profiles manually before they are fully public.

Time: 30 minutes to fill in; verification can take several days to a week.

Use the URL https://muckrack.com/your-name as your sameAs value. Verify by loading it in a private window — your profile should show your name, bio, beats, and recent articles publicly.',
			),

			'amazon_author' => array(
				'title' => 'Amazon Author Central',
				'body'  => 'Amazon Author Central gives every published author a public author page on Amazon, linking all your titles into one entity record. Because Amazon is one of the largest book retailers and its author pages are crawled heavily, this is a strong identifier for anyone with at least one book listed there.

Setup: go to authorcentral.amazon.com and sign in with the Amazon account you want to use long-term (not a personal shopping account if you can avoid it). Click "Add a Book" and search for one of your titles; Amazon will ask you to confirm you are the author. Approval is usually automatic within a few hours once your name on the book matches the name on your Author Central profile. Add the rest of your titles the same way. Then fill in the author bio, upload a professional photo, and add links to your website and social profiles.

Time: about 20 minutes if your books are already on Amazon.

Use the canonical URL of your author page as your sameAs value. The format is typically https://www.amazon.com/author/your-handle (preferred) or, if your account uses the older format, https://www.amazon.com/stores/Your-Name/author/BXXXXXXXXX. Verify by loading it in a private window — your author page should display your bio, photo, and the list of your books without a login.',
			),

			'goodreads' => array(
				'title' => 'Goodreads author page',
				'body'  => 'The Goodreads Author Program gives authors a verified profile linking all your books, reviews, and a Q&A section. Goodreads is owned by Amazon and its data feeds into broader book-entity systems, so an author page here reinforces the same identity Amazon Author Central is establishing.

Requirements: at least one of your books must already be listed on Goodreads (this happens automatically when a book is on Amazon or in major catalogs — search for your title to confirm). You also need a regular Goodreads reader account, which is free.

Process: log in to your Goodreads account, search for one of your books, and on the book page find the author name; click it to load the existing author entry; on that page click "Is this you?" If the link is not visible, scroll to the bottom of the page and use the "I am [author name], an author. Help me out!" link. Fill in the application form with your website, social profiles, and a short bio. A Goodreads librarian reviews the application; approval typically takes a few days to a week.

Time: 30 minutes to apply; days to weeks for approval; ongoing curation thereafter.

Use the URL https://www.goodreads.com/author/show/XXXX.Your_Name as your sameAs value. Verify by loading it in a private window — the page should show your books, bio, and the "Author" badge.',
			),

			'open_library' => array(
				'title' => 'Open Library author record',
				'body'  => 'Open Library is the Internet Archive\'s open catalog of books and authors. Records are openly editable and feed into broader bibliographic systems. For authors with library-cataloged books, an Open Library author record is a free and durable identifier with an OL number that does not change.

Process: go to openlibrary.org and search for your name and book titles. In most cases, an author record (https://openlibrary.org/authors/OLXXXXXXA) already exists because your books were added when libraries cataloged them. If yours exists, note the URL — you are done. If your books are present but the author record is missing or merged with someone else, create a free Open Library account and edit the records directly; the system is wiki-style and edits are immediate (though monitored).

If your books are not in Open Library at all, you (or a librarian) can add them manually with the "Add a book" workflow, then create the author record and link the books to it. Be accurate with ISBN, publisher, and date — wrong data gets reverted.

Time: 5 minutes if your record already exists; 30-60 minutes if you need to add books and create the record yourself.

Use the URL https://openlibrary.org/authors/OLXXXXXXA as your sameAs value. Verify by loading it in a private window — your author page should list your works and link to their individual records.',
			),

		);
	}

	/**
	 * Organization identifiers.
	 *
	 * @return array
	 */
	private static function org() {
		return array(

			'linkedin_company' => array(
				'title' => 'LinkedIn company page',
				'body'  => 'A LinkedIn company page is the most universally-recognized organization identifier on the open web. Setting one up is fast and the URL is stable.

Process: from your personal LinkedIn account (which must be a complete profile in good standing), go to the homepage and click the "For Business" icon in the top-right, then "Create a Company Page." Choose the page type that fits (Company, Showcase page, or Educational institution), and fill in the company name, vanity URL (linkedin.com/company/your-name), industry, organization size, type, website, and tagline. Upload a logo and cover image. LinkedIn will publish the page immediately; you can add detail (description, locations, products) afterwards.

Two tips that matter for entity authority specifically: make the public page visibility maximal (the "About" section should be visible to non-logged-in visitors), and use the exact organization name you use on your own site and in your schema. Mismatches reduce reconciliation confidence.

Time: 30 minutes to create and configure.

Use the URL https://www.linkedin.com/company/your-company/ (with the trailing slash) as your sameAs value. Verify by loading it in a private browser window — the public page should display your logo, name, and About section without a login wall.',
			),

			'wikidata' => array(
				'title' => 'Wikidata item (organization)',
				'body'  => 'A Wikidata item for your organization is, like the personal version, the strongest single entity-authority signal you can hold — and it follows the same notability rule. For organizations, Wikidata notability typically means independent reliable-source coverage: press articles about the company (not press releases by it), industry analyst reports, regulatory or government filings of substance, or a documented historical role. A small consultancy with no press coverage will not pass.

If your organization is notable: search wikidata.org first to confirm there is not already an item. If there is, note the Q-number. If there is not, the correct path is for someone other than an officer or employee to create it, because Wikidata\'s conflict-of-interest policy applies to organizations as well as people. Options: ask a customer, partner, or knowledgeable third party; post a request at the relevant WikiProject (WikiProject Companies, WikiProject Nonprofits, etc.); or hire a reputable Wikidata editor.

The item should be "instance of: business (Q4830453)" or a more specific subclass, with founding date, country, headquarters location, official website, and the identifiers it has (LEI, EIN, Companies House number, Crunchbase ID, etc.) attached as properties. The identifier properties are how the item gets reconciled across systems — without them the item is just a label.

Use https://www.wikidata.org/wiki/QXXXXX as your sameAs value. Verify the page loads publicly, lists the right identifiers, and points to the correct organization.',
			),

			'crunchbase' => array(
				'title' => 'Crunchbase organization profile',
				'body'  => 'Crunchbase started as a venture-funded-company tracker and has broadened into a general organization directory. It is widely crawled by business-information systems and is a credible identifier for companies, especially those with funding, acquisitions, or a measurable operational footprint.

Process: register a free account at crunchbase.com, then search for your organization. If a profile already exists (common — Crunchbase auto-creates from press releases and funding announcements), claim it via the "Suggest an Edit" or "Claim this profile" link on the page; ownership verification typically requires an email at the company domain. If no profile exists, create one from the "Add" menu in the top navigation, with the fields: company name, founding date, headquarters, brief description, website, and (if applicable) funding rounds and key people. The free tier is sufficient for the entity-authority purpose; paid plans add analyst features that do not change the URL.

Once published, fill the profile out completely — every additional field (industries, employee count, social links) reinforces the entity record.

Time: 30 to 60 minutes; verification of an existing profile takes a few business days.

Use the URL https://www.crunchbase.com/organization/your-company as your sameAs value. Verify by loading it in a private window — the public profile should display without prompting for a paid subscription.',
			),

			'x_twitter' => array(
				'title' => 'X (Twitter) profile',
				'body'  => 'An X (formerly Twitter) profile remains one of the most-recognized organization identifiers across schema systems, even with the platform\'s transitions. The setup is fast.

Process: create an account at x.com with your organization name as the display name and a handle that matches your organization (the @handle becomes part of the URL). Fill in the bio, location, website link, and upload a logo as the profile image. Honest current-state note: the blue verification check is now part of X Premium, a paid subscription rather than a free editorial process. For entity-authority purposes the verification badge is not strictly required — the consistent handle, complete bio, and outbound link to your website are what reconcile the identity. If your organization wants verification, X Premium for Business is the path; otherwise, a complete unverified profile still functions as a sameAs.

What does matter: do not let the profile go dormant. A profile last updated four years ago weakens the signal regardless of verification status. Even occasional activity tied to your organization\'s real news keeps it credible.

Time: 30 minutes for setup; ongoing maintenance.

Use the URL https://x.com/yourhandle (without "www." — X drops it) as your sameAs value. Verify by loading it in a private window — the profile should display publicly with your bio, link, and logo.',
			),

			'facebook' => array(
				'title' => 'Facebook page',
				'body'  => 'A Facebook organization page is widely recognized as an entity confirmation, even for organizations whose actual audience is not on Facebook. Setting one up is fast and the URL is durable.

Process: from a personal Facebook account, go to facebook.com/pages/create and choose the page type that fits (Business, Brand, Community, etc.). Fill in the page name (use your exact organization name), category, and a short description. Upload a profile picture (logo) and cover image. After the page is created, go to Settings -> Page Info and complete: full description, website, contact email, hours if applicable, and any other business details. The most important step for the entity-authority purpose is to set a username (vanity URL) under Settings -> Page Info -> Username; this changes the URL from a numeric ID to facebook.com/your-page.

Time: 20 minutes to create and configure.

Use the URL https://www.facebook.com/yourpage as your sameAs value. Verify by loading it in a private browser window (and in an actual incognito session — Facebook caches sometimes mask this) — the page should be publicly visible with your name, description, and link to your website, without a login prompt for the main page view.',
			),

			'youtube' => array(
				'title' => 'YouTube channel',
				'body'  => 'A YouTube channel for your organization is a recognized identifier even if video is not your primary medium, because YouTube channel URLs feed into many entity systems via the Google ecosystem.

Process: sign in to YouTube with a Google account and create a channel. For an organization, use a Brand Account (Google Account -> "Add another account" -> "Create a new account" -> Brand) so the channel is not tied to a single person\'s personal Google login. Set the channel name to match your organization, upload a profile picture and channel art, and fill in the channel "About" section with a description and links to your website and other profiles.

The default channel URL is an unfriendly numeric ID. To get a clean URL you need a custom handle (the @yourhandle format). Custom handles became available to all channels in 2022; set yours under Settings -> Channel -> Basic info -> Handle. The handle is unique and durable.

Time: 30 minutes to set up channel and handle; ongoing if you actually upload videos.

Use the URL https://www.youtube.com/@yourchannel as your sameAs value. Verify by loading it in a private window — the channel should display publicly with your logo, banner, and About section, without prompting for a sign-in.',
			),

		);
	}
}
