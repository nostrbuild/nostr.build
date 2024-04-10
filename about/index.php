<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UploadsData.class.php';

// Globals
global $link;

// Instantiate permissions class
$perm = new Permission();
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

  <link rel="stylesheet" href="/styles/index.css?v=9" />
  <link rel="stylesheet" href="/styles/header.css?v=10" />
  <link rel="stylesheet" href="/styles/tos.css?v=9" />
  <link rel="icon" href="https://cdn.nostr.build/assets/01.png" />

  <title>nostr.build - about</title>
</head>

<body>
  <header class="header">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
  </header>

  <style>
    img {
      width: 100%;
      height: auto;
    }
  </style>
  <main class="about_page">
    <h2>nostr.build</h2>
    <h1>About Us</h1>
    <h3>Short history</h3>
    <section class="paragraph">
      <p>
        nostr.build started as a free, community focused, media (image, gif
        and video) uploader for the nostr social media platform on December
        24th, 2022. Check out our
        <a class="ref_link" href="/v1">original website</a> before
        <a class="ref_link" href="https://snort.social/p/npub1uapy44zhu5f0markfftt7m2z3gr2zwssq6h3lw8qlce0d5pjvhrs3q9pmv">@rob</a> and <a class="ref_link" href="https://snort.social/p/npub15s8w3yrmswtta4n7rkac47jetntyx6ar6lq4h73fsskqacwvsfdsvaallg">@nahuelg5</a> completely redesigned it!
        nostr.build’s main purpose is to help grow the nostr ecosystem by
        providing a free, web based, API or application integrated way to
        upload user’s favorite media. We believe in a transparent, community
        approach to media moderation and <a class="ref_link" href="https://github.com/nostrbuild/nostr.build" target="_blank">open source code.</a>
      </p>

      <a href="/v1" target="_blank"><img class="about_img" src="https://cdn.nostr.build/assets/about/nostrbuild_v1.png" alt="nostr.build v1" height="300" /></a>
    </section>

    <p>Some of nostr.build’s key principles include:</p>
    <p>
      - Always provide a free service that will keep uploaded media as
      accurate and as long as possible, ’Archived!’<br />
      - No ads! No commercial advertisements!<br />
      - Only community projects, nostr builders, educators, creators, developers, artists and
      memes. Just ask us.<br />
      - Provide value for value to users including new features, integrations,
      support, and responsive communication.<br />
    </p>
    <h3>Free services</h3>
    <p>
      nostr.build’s key objective is to offer a free, no ads, reliable, long
      time storage of media for the nostr community.<br />
      We will also continue to add additional features to our free service,
      including:<br />

      - Media uploads up to (10MB) hosted on Cloudflare<br />
      - <a class="ref_link" href="https://www.cloudflare.com/" target="_blank">Cloudflare</a> global CDN network for images and GIFs<br />
      - No ads! Only community projects, nostr builders, creators, devs,
      artists and memes<br />
      - Profile picture uploader that properly shrinks, crops and puts your PP
      in a hidden folder<br />
      - API for all nostr applications, projects and developers<br /><br />

      nostr.build was recently honored to be part of the first wave of nostr
      devs to receive a grant from the <a class="ref_link" href="https://opensats.org/blog/nostr-grants-july-2023">OpenSats public charity</a> focused on
      Bitcoin and Nostr projects. Although this will help greatly with
      R&D, new projects, growth and overages, the goal is to keep nostr.build
      ‘self-sustainable’ through account earnings.<br /><br />
      ** People often ask, “how do you cover costs for free services?"<br>
    </p>

    <figure class="img_container double_img">
      <a href="https://opensats.org/blog/nostr-grants-july-2023" target="_blank"><img class="about_img img_horizontal" src="https://cdn.nostr.build/assets/about/opensats.png" alt="opensats grant" height="80" /></a>
      <a href="https://github.com/nostrbuild/nostr.build" target="_blank"><img class="about_img img_horizontal" src="https://cdn.nostr.build/assets/about/githublogo.png" alt="GitHub" height="80" /></a>
    </figure>
    <h3>Paid accounts</h3>
    <section class="paragraph para_square">
      <a href="https://nostr.build/plans/"><img class="about_img img_square" src="https://cdn.nostr.build/assets/primo_nostr.png" alt="nostr.build account logo" width="80" /></a>
      <p>
        nostr.build offers accounts with premium features charged annually.
        Accounts can be purchased with a lighting wallet or Bitcoin. Proceeds
        are kept on <a class="ref_link" href="https://amboss.space/node/02e869c409bd62ca84e9306ad96d9daef3b2b31a1c777b501fc55f2c09969ce1a3">pay.nostr.build lighting node</a> and used for
        all nostr.build expenses: AWS, CDN, storage, development, domains, etc.
      </p>
    </section>

    <p>
      ** Purchasing an account not only gives you premium features, you are
      also supporting a free, no ads, open source service for all of nostr!<br />

      Account features include:<br />
      - Cloudflare CDN network for all media including videos<br />
      - No max on file size upload - account sizes 10/20/50GB<br />
      - Post you media to the Creators page on nostr.build<br />
      - Ability to easily ‘Delete’ media after posted<br />
      - Private folders not seen in the View All gallery<br />
      - Access to View All free media ever uploaded to nostr.build, over 500k images, GIFs and videos!<br />
      ** See all <a class="ref_link" href="https://nostr.build/plans/">nostr.build Accounts</a> and features<br />
    </p>
    <figure class="img_container double_img">
      <img class="about_img img_horizontal" src="https://cdn.nostr.build/assets/about/awss3.png" alt="aws logo" width="180" />
      <img class="about_img img_horizontal" src="https://cdn.nostr.build/assets/about/cf.png" alt="cloudflare logo" width="180" />
    </figure>

    <h4>How to use Account related features:</h4>
    <section class="paragraph">
      <p>
        - First, purchase an account <a class="ref_link" href="https://nostr.build/plans/">HERE!</a><br />
        - Folders: Click on ‘New Folder’ in the left menu bar, name your folder and click Create.<br />
        - Move media to a folder: ‘checkbox’ the media you want to move, and
        ‘Double Click’ the folder to move it there.<br />
        - Delete a file or folder: ‘checkbox’ what you want to
        delete, click the red Delete button in the bottom left menu bar.<br />
        - ** If you delete a folder with files in it, those files just move to
        the ‘no folder’ section, they are NOT deleted.<br />
        - ‘View All’ allows you to see all free uploads ever on
        nostr.build, excluding paid accounts and profile pictures. All media
        must align to nostr.build's <a class="ref_link" href="https://nostr.build/tos/" target="_blank">Terms of Service</a> <br />
        - If you purchased a Creators account, you can make your media
        publically available on the <a class="ref_link" href="https://nostr.build/creators/" target="_blank">nostr.build Creators page</a><br />
      </p>

      <figure class="img_container double_img">
        <img src="https://cdn.nostr.build/assets/about/createfolder.png" alt="create a folder" class="about_img" />
        <img src="https://cdn.nostr.build/assets/about/movedelete.png" alt="move and delete" class="about_img" />
      </figure>
    </section>

    <h3>Contacts</h3>
    <figure class="img_container double_img">
      <a href="https://snort.social/p/npub1nxy4qpqnld6kmpphjykvx2lqwvxmuxluddwjamm4nc29ds3elyzsm5avr7"><img src="https://cdn.nostr.build/assets/about/nostrbuild.png" alt="nostr.build" class="about_img img_square" /></a>
      <img src="/assets/about/community.png" alt="community" class="about_img img_square" />
      <a href="https://snort.social/p/npub137c5pd8gmhhe0njtsgwjgunc5xjr2vmzvglkgqs5sjeh972gqqxqjak37w"><img src="https://cdn.nostr.build/assets/about/fishcake.png" alt="fishcake" class="about_img img_square" /></a>
    </figure>
  </main>

  <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
  <script src="/scripts/index.js?v=8"></script>
</body>

</html>
