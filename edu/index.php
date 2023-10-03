<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="robots" content="noindex" />
  <meta name="keywords" content="nostr, damus image uploader, image link, snort.social, astril.ninja, image, uploader, media upload, damus pictures, video uploader,nostr repository " />
  <meta name="description" content="Image, video and media uploader for nostr, damus, astral.ninja, snort.social, and most all nostr clients. Upload any kind of media and get a link to post, or use our iOS app to automatically uppload images straight from your keyboard." />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="stylesheet" href="/styles/index.css?v=3" />
  <link rel="stylesheet" href="/styles/header.css?v=4" />
  <link rel="stylesheet" href="/styles/tos.css?v=3" />
  <link rel="icon" href="https://cdn.nostr.build/assets/01.png" />

  <title>nostr.build - edu</title>
  <style>
    img {
      width: 100%;
      height: auto;
    }
  </style>
</head>

<body>
  <header class="header">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
  </header>

  <main class="about_page">
    <h2>nostr.build</h2>
    <h1>Edu</h1>

    <figure class="img_container double_img">
      <img class="about_img img_horizontal" src="https://cdn.nostr.build/assets/edu/btc_edu01.png" alt="BTC Education" width="180" />
    </figure>
    <h3>Bitcoin's Sound Money Properties Series</h3>
    <p> Slide decks by <a class="ref_link" href="https://thesimplestbitcoinbook.net" target="_blank">Keysa @SimplestBTCBook</a></p><br />
    <p>
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/1_PORTABLE_DURABLE_DIVISIBLE_FUNGIBLE.pdf" target="_blank">#1: Portable, Durable, Divisible, Fungible</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/2_SCARCITY_PROPERTIES.pdf" target="_blank">#2: Scarcity & Hard Cap Supply</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/3_DISTRIBUTE_DECENTRALIZED.pdf" target="_blank">#3: Distributed & Decentralized</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/4_CENSORSHIP_RESISTANT_UNCONFISCATABLE.pdf" target="_blank">#4: Censorship Resistant & Unconfiscatable</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/5_IMMUTABLE_INCORRUPTIBLE.pdf" target="_blank">#5: Immutable & Incorruptible</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/6_EASILY_VERIFIABLEAND_CANT_BE_COUNTERFEITED.pdf" target="_blank">#6: Easily Verifiable & Can’t be Counterfeited</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/7_PERMISSIONLESS_FRICTIONLESS_AND_PEER2PEER.pdf" target="_blank">#7: Peer-to-Peer, Permissionless & Frictionless</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/8_NEUTRAL_AND_VOLUNTARY.pdf" target="_blank">#8: Neutral & Voluntary</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/9_TRANSPARENT_OPENSOURCE_AND_AUDITABLE.pdf" target="_blank">#9: Transparent, Open Source & Auditable</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/10_BORDERLESS.pdf" target="_blank">#10: Borderless</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/11_PROVIDES_SETTLEMENT_FINALITY.pdf" target="_blank">#11: Provides Settlement Finality & Is a Bearer Asset</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/12_PSUEDONYM_US_AND_TRUSTLESS.pdf" target="_blank">#12: Pseudonymous & Trustless</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/13_SECURE_AND_SCALABLE.pdf" target="_blank">#13: Secure & Scalable</a><br />
      <a class="ref_link" href="https://cdn.nostr.build/assets/edu/14_DISINFLATIONARY_DEFLATIONARY.pdf" target="_blank">#14: Disinflationary/Deflationary</a><br /><br />
    </p>

    <p> Roll Your Own Seed Worksop with <a class="ref_link" href="https://dplusplus.me" target="_blank">@D++</a></p><br />
    <p><a class="ref_link" href="https://cdn.nostr.build/assets/edu/ROLL_YOUR_OWN_SEED_WORKSHOP.pdf" target="_blank">Roll Your Own Seed Workshop</a><br />
    </p>

  </main>

  <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
  <script src="/scripts/index.js?v=2"></script>
</body>

</html>