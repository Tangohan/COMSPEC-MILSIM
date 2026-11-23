<?php

declare(strict_types=1);

/*
 * Athena Code of Conduct (English). Same structure as lang/fr/conduct.php: keep article numbers identical.
 * Body format: paragraphs separated by a blank line, bullets on lines starting with "- ".
 * References: [[art:N]] [[contact]] [[appeal]] [[rights]] [[cgu]] [[mentions]] [[privacy]].
 */
return [
    'title' => 'Athena Code of Conduct',
    'nav' => 'Code of Conduct',
    'intro' => 'These rules apply to interactions, content and use of Athena services. They supplement the Terms of Use and the rules of each community.',
    'intro_scope' => 'The word "Admin" means an administrator of the whole Athena platform. A "community administrator" manages only their own community (see [[art:9]]).',
    'index' => 'List of articles',
    'article' => 'Article :number',
    'version_label' => 'Version',
    'version' => '2.0',
    'updated_label' => 'Last updated',
    'updated' => '23 November 2026',
    'updated_iso' => '2026-11-23',
    'print' => 'Print or save as PDF',
    'search_label' => 'Search the articles',
    'search_placeholder' => 'Word or phrase (example: appeal)',
    'search_none' => 'No article matches this search.',
    'search_count' => ':count article(s) shown',
    'example' => 'Example',
    'permalink' => 'Permanent link to :title',
    'back_top' => 'Back to the list',
    'summary_label' => 'In short',

    'article_1_title' => 'Respect for others',
    'article_1_summary' => 'Treat everyone with respect, even when you disagree. Threats, targeted insults, discrimination and harassment are prohibited, including in private messages.',
    'article_1_body' => "Disagreement is normal, personal attacks are not. The following are prohibited:\n- threats and insults aimed at a person;\n- discriminatory, hateful or defamatory remarks;\n- harassment: repeated messages, concerted exclusion, publishing information to cause harm.\n\nIf someone asks you to end a conversation, respect that request. This also applies on services linked to Athena (Discord, Steam, game servers) when the incident involves platform members.",
    'article_1_example' => 'A member messages another member every day after being asked to stop. This is harassment, even if every message is polite.',

    'article_2_title' => 'Content and privacy',
    'article_2_summary' => 'Share only what you have the right to distribute. No personal information, private conversations or community documents without permission. Illegal content is prohibited.',
    'article_2_body' => "You remain responsible for what you publish (messages, images, documents, orders, reports). You guarantee that you have the right to distribute it.\n\nDo not publish, without permission from the people or administrators concerned:\n- personal information (name, address, photo, phone number);\n- private conversations or screenshots of private messages;\n- internal community documents (rosters, ORBAT, orders, files).\n\nIllegal content is prohibited: incitement to hatred or violence, child sexual abuse material, counterfeiting, defamation, stolen data. The publisher may remove or block access to any manifestly illegal content that is reported or detected (see [[mentions]]).",
    'article_2_example' => 'Posting a screenshot of a private conversation, with a member’s name, to mock them is prohibited, even if the conversation is genuine.',

    'article_3_title' => 'Accounts and security',
    'article_3_summary' => 'One account, one person. Protect your credentials. Impersonation, account sharing and circumventing a sanction are prohibited. Report suspicious access.',
    'article_3_body' => "Your account is personal and you are responsible for what is done from it. It is prohibited to:\n- lend, sell or share your account, your linking keys or your game keys;\n- use someone else’s account without their permission;\n- impersonate a person, a community administrator, an Admin or the Athena team;\n- create or use another account to get around a block, suspension or ban.\n\nNever give out your password, even to someone claiming to be the Athena team. If you think your account is being used without your knowledge, change your password and then [[contact]] without delay.",
    'article_3_example' => 'A suspended member registers again with another email address to come back: this circumvents a sanction and may lead to a permanent ban.',

    'article_4_title' => 'Fair use of the platform',
    'article_4_summary' => 'Respect how the service works: no spam, fraud, unauthorised access, malicious code or bulk extraction. APIs may only be used as intended.',
    'article_4_body' => "The following are prohibited:\n- spam and unsolicited automated messages;\n- fraud and false statements (false attendance, false reports);\n- attempts at unauthorised access to an account, a community or data;\n- introducing malicious code and any action that disrupts the service;\n- bulk data extraction (scraping pages, profiles or rosters) without permission.\n\nAthena’s interfaces (APIs) are meant for the official applications (website, bot, mod). If you build a tool that connects to them, first ask the team for permission ([[contact]]), respect rate limits and use only the access you were given.\n\nIf you find a vulnerability, do not exploit it beyond what is needed to confirm it, do not disclose the data it exposes and report it to the team.",
    'article_4_example' => 'A script that automatically downloads the full member list of another community is bulk extraction, even if the pages are technically reachable.',

    'article_5_title' => 'Reporting incidents',
    'article_5_summary' => 'Report incidents to your community moderators, or to the Athena team for platform matters. Give facts, dates and links. Do not share the report publicly.',
    'article_5_body' => "Who to report to:\n- an incident between members of the same community: that community’s moderators or administrators;\n- illegal content, a hacked account, a platform issue or an issue involving a community administrator: the Athena team, through [[contact]].\n\nInclude the facts, dates, people involved and any useful links or screenshots. Respect the privacy of the people involved and avoid sharing the reported content publicly.\n\nA report made in good faith is never sanctioned, even if it turns out to be unfounded. A knowingly false report made to cause harm falls under [[art:1]].",
    'article_5_example' => 'Useful report: "On 12 November around 9 pm, in the general channel, member X posted Y’s address. Message link: ..."',

    'article_6_title' => 'Sanctions, decisions and appeals',
    'article_6_summary' => 'Measures are proportionate to the seriousness of the facts. The notice states the reason, the article concerned and the duration. You can request a review when appeals are open.',
    'article_6_body' => "Scale of measures. Moderators choose the lightest measure that is enough to stop the problem:\n- warning: a reminder of the rule, with no restriction;\n- removal of content or restriction of a feature;\n- temporary suspension of account access, with an end date shown in the notice;\n- permanent ban: account access closed, with no end date.\n\nCriteria taken into account: seriousness of the facts, repetition, intent, harm caused to people or to the platform, and the attitude of the person concerned. The most serious facts (threats, illegal content, hacking, harm to the security of the service) may justify an immediate suspension or ban, without a prior warning. Athena may also apply an automatic rule: several confirmed suspensions or bans within a given period lead to a new suspension, whose reason states the rule applied.\n\nInformation. The notice states the reason, the article of this code concerned, the end date if any, and whether appeals are open.\n\nAppeal. To view your decision and appeal it, go to [[appeal]]:\n- enter the email address of the account: a private link, valid for one hour, is sent to you;\n- explain your situation in 10 to 4,000 characters, with any useful context;\n- the platform administration reviews the request, where possible by a person other than the one who made the decision;\n- the outcome of the review is sent to you with a reasoned reply.\n\nThe administration may close an appeal, for example after a final decision: the notice then says so. Measures taken by a community’s moderators follow that community’s rules, which must allow you to appeal to the community.",
    'article_6_example' => 'A first insulting message usually results in a warning or removal of the message. Repeated threats against a member may lead directly to a suspension or a ban.',

    'article_7_title' => 'Inactivity and returning to Athena',
    'article_7_summary' => 'An account with no activity for a long period may be suspended. This is not a disciplinary sanction: you can ask to resume your activity.',
    'article_7_body' => "Athena may suspend access to an account with no recorded sign-in or game session for an extended period, or with no recorded activity since registration. This measure protects abandoned accounts and limits data kept without purpose.\n\nCheck your notice for the reason and duration. If you would like to return or correct an error (for example a sign-in that was not counted), request a review when that option is available, following the procedure in [[art:6]].",
    'article_7_example' => 'You played on a server without ever signing in to the website and your account is suspended: explain this in your review request.',

    'article_8_title' => 'Game, mod and game keys',
    'article_8_summary' => 'The mod and the keys tied to your account are personal. Cheating, reverse engineering and bypassing the official module are prohibited.',
    'article_8_body' => "Athena’s Arma 3 modules (including ATAK) and related services (ORBAT, SSE, session tracking) exchange game data with the platform. To keep this data reliable, it is prohibited to:\n- cheat or falsify game data (positions, attendance, results, ORBAT or SSE states);\n- bypass, disable or modify the official module, or pretend it is active;\n- decompile or analyse the mod or its exchanges with the platform to extract secrets or reproduce the service (reverse engineering);\n- share, resell or publish your game and linking keys;\n- connect to the platform with an unofficial client that imitates the mod.\n\nGame data tied to your account may be consulted by authorised people when cheating is suspected (see [[art:11]]).",
    'article_8_example' => 'Using a program that sends fake positions to the server to appear on the tactical map is cheating.',

    'article_9_title' => 'Communities and community administrators',
    'article_9_summary' => 'Each community sets its own internal rules, within this code. Its administrators moderate it. Only platform Admins act across the whole platform.',
    'article_9_body' => "Each community may add its own rules (civility, organisation, in-game discipline). They cannot contradict this code or the [[cgu]].\n\nCommunity administrators may moderate, remove or restrict access to the content and spaces of their community. They have no power over other communities or over access to the platform: only Athena administration (the “Admins”) can suspend or ban a platform account.\n\nA community administrator handles members’ data with discretion: they use it only to run their community and do not disclose it outside.\n\nA community account (unit, page, account run by a team) must have an identified person in charge and cannot be used to get around the rules for personal accounts.",
    'article_9_example' => 'A community administrator can remove a member from their unit. They cannot suspend that member’s Athena account: they report the situation to the Athena team.',

    'article_10_title' => 'Intellectual property',
    'article_10_summary' => 'Athena’s mods, software and brands remain with their authors. You keep your rights over your content and allow Athena to display it.',
    'article_10_body' => "Athena’s software, mods, interfaces, texts, brands and databases remain the property of the publisher or of their authors. You may use the mods as the platform intends. You may not copy, republish, modify to redistribute or resell them without written permission.\n\nSome elements belong to other rights holders (Arma 3, Steam Workshop, third-party mod authors): respect their licences.\n\nYou keep your rights over your own content. By publishing it on Athena, you allow the platform to store it and display it to the people you intend it for, so that the service can work.",
    'article_10_example' => 'Republishing the Athena mod pack on another site without permission is not allowed.',

    'article_11_title' => 'Personal data, logs and rights',
    'article_11_summary' => 'Athena keeps logs for security and moderation. You can ask for access, rectification, erasure, portability, restriction and objection.',
    'article_11_body' => "To keep the service secure, apply this code and handle reports, Athena keeps technical logs (sign-ins, moderation actions, decisions and appeals) and game data tied to your account. Only authorised people access them, for these purposes. Retention is limited to what is necessary; see [[privacy]].\n\nYou may exercise your rights of access, rectification, erasure, portability, restriction and objection, and withdraw any consent given, from [[rights]] or by writing to [[contact]]. You may also lodge a complaint with the CNIL. Some data (for example data proving a sanction or meeting a legal obligation) may be kept despite an erasure request, for as long as strictly necessary.\n\nTechnical providers and linked services are described in [[mentions]] and in the personal data policy.",
    'article_11_example' => 'To get a copy of your account data or have your account deleted, use the “Exercise your rights” page.',

    'article_12_title' => 'Contact',
    'article_12_summary' => 'One address for questions, reports and data requests: [[contact]].',
    'article_12_body' => "For a question about this code, a report, a data request or a security incident, write to [[contact]]. Give the email address of your account, but never send your password.\n\nTo appeal a measure, use [[appeal]].",
    'article_12_example' => '',

    'article_13_title' => 'Changes to this code',
    'article_13_summary' => 'This code may change. The version in force is the one on this page, with its number and date. Article numbers stay stable.',
    'article_13_body' => "This code may be amended to reflect changes in the service, the law or community life. The version in force is published on this page, with its number and last update date. Article numbers do not change between versions: new articles are added at the end, so that decisions already notified always point to the right text.\n\nContinuing to use the service after a new version is published means you accept it. See also [[cgu]].",
    'article_13_example' => '',

    'read_rules' => 'Read the Athena Code of Conduct',
    'contact' => 'Contact support',
    'appeal' => 'View or appeal a decision',
    'link_appeal' => 'the appeal page',
    'link_rights' => 'the “Exercise your rights” page',
    'link_cgu' => 'the Terms of Use',
    'link_mentions' => 'the legal notice',
    'link_privacy' => 'the personal data policy',
];
