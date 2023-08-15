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
    <!-- Google tag (gtag.js) -->
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="robots" content="noindex" />
    <meta
      name="keywords"
      content="nostr, damus image uploader, image link, snort.social, astril.ninja, image, uploader, media upload, damus pictures, video uploader,nostr repository "
    />
    <meta
      name="description"
      content="Image, video and media uploader for nostr, damus, astral.ninja, snort.social, and most all nostr clients. Upload any kind of media and get a link to post, or use our iOS app to automatically uppload images straight from your keyboard."
    />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="stylesheet" href="/styles/index.css" />
    <link rel="stylesheet" href="/styles/header.css" />
    <link rel="stylesheet" href="/styles/tos.css" />
    <link rel="icon" href="/assets/01.png" />

    <title>nostr.build - about</title>
  </head>

  <body>
    <header class="header">
      <nav class="navigation_header">
        <a href="/" class="nav_button active_button">
          <span>
            <svg
              width="21"
              height="20"
              viewBox="0 0 21 20"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                opacity="0.12"
                d="M8 17.5V10H13V17.5"
                fill="url(#paint0_linear_220_743)"
              />
              <path
                d="M7.99999 17.5V11.3333C7.99999 10.8666 7.99999 10.6332 8.09082 10.455C8.17071 10.2982 8.2982 10.1707 8.455 10.0908C8.63326 10 8.86666 10 9.33332 10H11.6667C12.1334 10 12.3667 10 12.545 10.0908C12.7018 10.1707 12.8292 10.2982 12.9092 10.455C13 10.6332 13 10.8666 13 11.3333V17.5M2.16666 7.91667L9.69999 2.26667C9.98691 2.05151 10.1303 1.94392 10.2878 1.90245C10.4269 1.86585 10.5731 1.86585 10.7122 1.90246C10.8697 1.94392 11.0131 2.05151 11.3 2.26667L18.8333 7.91667M3.83332 6.66667V14.8333C3.83332 15.7667 3.83332 16.2335 4.01498 16.59C4.17477 16.9036 4.42974 17.1586 4.74334 17.3183C5.09986 17.5 5.56657 17.5 6.49999 17.5H14.5C15.4334 17.5 15.9002 17.5 16.2567 17.3183C16.5702 17.1586 16.8252 16.9036 16.985 16.59C17.1667 16.2335 17.1667 15.7667 17.1667 14.8333V6.66667L12.1 2.86667C11.5262 2.43634 11.2393 2.22118 10.9242 2.13824C10.6462 2.06503 10.3538 2.06503 10.0757 2.13824C9.76066 2.22118 9.47374 2.43634 8.89999 2.86667L3.83332 6.66667Z"
                stroke="url(#paint1_linear_220_743)"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <defs>
                <linearGradient
                  id="paint0_linear_220_743"
                  x1="10.5033"
                  y1="12.1603"
                  x2="9.92909"
                  y2="17.4375"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="white" />
                  <stop offset="1" stop-color="#884EA4" />
                </linearGradient>
                <linearGradient
                  id="paint1_linear_220_743"
                  x1="10.5111"
                  y1="6.37568"
                  x2="9.75801"
                  y2="17.4488"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="white" />
                  <stop offset="1" stop-color="#884EA4" />
                </linearGradient>
              </defs>
            </svg>
          </span>
          Home
        </a>
        <a href="/builders" class="nav_button">
          <span>
            <svg
              width="21"
              height="20"
              viewBox="0 0 21 20"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M14.0774 5.24426C14.4029 4.91882 14.9305 4.91882 15.2559 5.24426L19.4226 9.41092C19.748 9.73633 19.748 10.264 19.4226 10.5894L15.2559 14.7561C14.9305 15.0815 14.4029 15.0815 14.0774 14.7561C13.752 14.4307 13.752 13.903 14.0774 13.5776L17.6549 10.0002L14.0774 6.42277C13.752 6.09733 13.752 5.56969 14.0774 5.24426Z"
                fill="url(#paint0_linear_220_715)"
              />
              <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M6.92257 5.24426C7.24801 5.56969 7.24801 6.09733 6.92257 6.42277L3.34516 10.0002L6.92257 13.5776C7.24801 13.903 7.24801 14.4307 6.92257 14.7561C6.59713 15.0815 6.0695 15.0815 5.74406 14.7561L1.57739 10.5894C1.25195 10.264 1.25195 9.73633 1.57739 9.41092L5.74406 5.24426C6.0695 4.91882 6.59713 4.91882 6.92257 5.24426Z"
                fill="url(#paint1_linear_220_715)"
              />
              <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M12.3474 1.68669C12.7967 1.78652 13.08 2.23167 12.9802 2.68095L9.64683 17.6809C9.547 18.1302 9.10183 18.4135 8.65256 18.3137C8.20328 18.2138 7.92 17.7687 8.01984 17.3194L11.3532 2.3194C11.453 1.87012 11.8982 1.58685 12.3474 1.68669Z"
                fill="url(#paint2_linear_220_715)"
              />
              <defs>
                <linearGradient
                  id="paint0_linear_220_715"
                  x1="16.75"
                  y1="5.00018"
                  x2="16.75"
                  y2="15.0001"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#2EDF95" />
                  <stop offset="1" stop-color="#07847C" />
                </linearGradient>
                <linearGradient
                  id="paint1_linear_220_715"
                  x1="4.24998"
                  y1="5.00018"
                  x2="4.24998"
                  y2="15.0001"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#2EDF95" />
                  <stop offset="1" stop-color="#07847C" />
                </linearGradient>
                <linearGradient
                  id="paint2_linear_220_715"
                  x1="10.5"
                  y1="1.66667"
                  x2="10.5"
                  y2="18.3337"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#2EDF95" />
                  <stop offset="1" stop-color="#07847C" />
                </linearGradient>
              </defs>
            </svg>
          </span>
          Builders
        </a>
        <a href="/creators" class="nav_button">
          <span>
            <svg
              width="21"
              height="20"
              viewBox="0 0 21 20"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                opacity="0.12"
                d="M2.16669 10C2.16669 14.6023 5.89765 18.3333 10.5 18.3333C11.8808 18.3333 13 17.2141 13 15.8333V15.4167C13 15.0297 13 14.8362 13.0214 14.6737C13.1691 13.5518 14.0519 12.6691 15.1737 12.5214C15.3362 12.5 15.5297 12.5 15.9167 12.5H16.3334C17.7141 12.5 18.8334 11.3807 18.8334 10C18.8334 5.39763 15.1024 1.66667 10.5 1.66667C5.89765 1.66667 2.16669 5.39763 2.16669 10Z"
                fill="url(#paint0_linear_220_726)"
              />
              <path
                d="M2.16669 10C2.16669 14.6023 5.89765 18.3333 10.5 18.3333C11.8808 18.3333 13 17.2141 13 15.8333V15.4167C13 15.0297 13 14.8362 13.0214 14.6737C13.1691 13.5518 14.0519 12.6691 15.1737 12.5214C15.3362 12.5 15.5297 12.5 15.9167 12.5H16.3334C17.7141 12.5 18.8334 11.3808 18.8334 10C18.8334 5.39763 15.1024 1.66667 10.5 1.66667C5.89765 1.66667 2.16669 5.39763 2.16669 10Z"
                stroke="url(#paint1_linear_220_726)"
                stroke-width="1.66667"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M6.33333 10.8333C6.79357 10.8333 7.16667 10.4603 7.16667 10C7.16667 9.53975 6.79357 9.16667 6.33333 9.16667C5.8731 9.16667 5.5 9.53975 5.5 10C5.5 10.4603 5.8731 10.8333 6.33333 10.8333Z"
                stroke="url(#paint2_linear_220_726)"
                stroke-width="1.66667"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M13.8333 7.5C14.2936 7.5 14.6667 7.1269 14.6667 6.66667C14.6667 6.20643 14.2936 5.83333 13.8333 5.83333C13.3731 5.83333 13 6.20643 13 6.66667C13 7.1269 13.3731 7.5 13.8333 7.5Z"
                stroke="url(#paint3_linear_220_726)"
                stroke-width="1.66667"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M8.83333 6.66667C9.29358 6.66667 9.66667 6.29357 9.66667 5.83333C9.66667 5.3731 9.29358 5 8.83333 5C8.3731 5 8 5.3731 8 5.83333C8 6.29357 8.3731 6.66667 8.83333 6.66667Z"
                stroke="url(#paint4_linear_220_726)"
                stroke-width="1.66667"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <defs>
                <linearGradient
                  id="paint0_linear_220_726"
                  x1="2.16669"
                  y1="1.66667"
                  x2="21.7341"
                  y2="6.43848"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#DABD55" />
                  <stop offset="1" stop-color="#F78533" />
                </linearGradient>
                <linearGradient
                  id="paint1_linear_220_726"
                  x1="2.16669"
                  y1="1.66667"
                  x2="21.7341"
                  y2="6.43848"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#DABD55" />
                  <stop offset="1" stop-color="#F78533" />
                </linearGradient>
                <linearGradient
                  id="paint2_linear_220_726"
                  x1="5.5"
                  y1="9.16667"
                  x2="7.45674"
                  y2="9.64385"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#DABD55" />
                  <stop offset="1" stop-color="#F78533" />
                </linearGradient>
                <linearGradient
                  id="paint3_linear_220_726"
                  x1="13"
                  y1="5.83333"
                  x2="14.9567"
                  y2="6.31052"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#DABD55" />
                  <stop offset="1" stop-color="#F78533" />
                </linearGradient>
                <linearGradient
                  id="paint4_linear_220_726"
                  x1="8"
                  y1="5"
                  x2="9.95674"
                  y2="5.47718"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#DABD55" />
                  <stop offset="1" stop-color="#F78533" />
                </linearGradient>
              </defs>
            </svg>
          </span>
          Creators
        </a>

        <a href="/register" class="nav_button">
          <span><img src="/assets/nav/login.png" alt="login image" /> </span>
          <?= $perm->isGuest() ? 'Login' : 'Account' ?>
        </a>
      </nav>
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
          24th, 2022. Check out our original website
          <a class="ref_link" href="/v1">HERE</a> before
          <a class="ref_link" href="/v1">@rob</a> provided the design updates!
          nostr.build’s main purpose is to help grow the nostr ecosystem by
          providing a free web based, API or application integrated way to
          upload user’s favorite media. We believe in a transparent, community
          approach to media moderation and open source code
          <a class="ref_link" href="https://github.com/nostrbuild/nostr.build"
            >HERE</a
          >.
        </p>

        <img class="about_img" src="/assets/about/nostrbuild_v1.png" alt="nostr.build v1" height="300"/>
      </section>

      <p>Some of nostr.build’s key principles include:</p>
      <p>
        - Always provide a free service that will keep uploaded media as
        accurate and as long as possible.’Archived!’<br />
        - No ads! No commercial advertisements!<br />
        - Only community projects, nostr builders, creators, devs, artists and
        memes. Just ask us.<br />
        - Provide value for value to users including new features, integrations,
        support, and responsive communication.<br />
      </p>
      <h3>Free services</h3>
      <p>
        Nostr.build’s key objective is to offer a free, no ads, reliable, long
        time storage of media for the nostr community.<br />
        We will also continue to add additional features to our free service,
        including:<br />

        - Image, gif, audio and video uploads up to 25MB hosted on the AWS S3<br />
        - Global, lightning fast CDN network for all images and GIFs using
        Bunny.net<br />
        - No ads! Only community projects, nostr builders, creators, devs,
        artists and memes<br />
        - Profile picture uploader that properly shrinks, crops and puts your PP
        in a hidden folder<br />
        - API for all nostr applications, projects and developers<br /><br />

        nostr.build was recently honored to be part of the first wave of nostr
        devs to receive a grant from the OpenSats public charity focused on
        Bitcoin and Nostr projects, HERE. Although this will help greatly with
        R&D, new projects, growth and overages, the goal is to keep nostr.build
        ‘self-sustainable’ through account earnings.<br /><br />
        ** People often ask, “how do you cover the charges for your free
        services? Paid Accounts.<br /><br />
      </p>
      <figure class="img_container double_img">
        <img class="about_img img_horizontal" src="/assets/about/opensats.png" alt="opensats grant" height="80"/>
        <img class="about_img img_horizontal" src="/assets/about/githublogo.png" alt="opensats grant" height="80"/>
      </figure>
      <h3>Paid accounts</h3>
      <section class="paragraph para_square">
        <img class="about_img img_square" src="/assets/primo_nostr.png" alt="nostr.build account logo" width="80"/>
        <p>
          nostr.build offers accounts with premium features, charged annually.
          Accounts can be purchased with a lighting wallet or Bitcoin. Proceeds
          are kept on the pay.nostr.build lighting node HERE, and used to pay
          for all nostr.build expenses: AWS, CDN, storage, domains, and other
          related expenses.
        </p>
      </section>

      <p>
        ** Purchasing an account not only gives you premium features, you are
        also supporting a free, ads-free, open source, service for all of nostr
        and decentralized social media!<br />

        Account features include:<br />
        - Blazing fast global CDN (Bunny.net) on the AWS S3 for all images and
        videos<br />
        - Unlimited file-size for media uploads (up to your account size
        5/10/20GB)<br />
        - Post you media to the Creators page on nostr.build<br />
        - Ability to easily ‘Delete’ media after posted<br />
        - Private folders not seen in the View All gallery<br />
        - Access to View All free media ever uploaded to nostr.build, over 450k
        images, gifs and videos!<br />
        ** See all nostr.build Accounts and features, HERE!
      </p>
      <figure class="img_container double_img">
        <img class="about_img img_horizontal" src="/assets/about/awss3.png" alt="nostr.build account logo" width="180"/>
        <img class="about_img img_horizontal" src="/assets/about/bunnynet.png" alt="nostr.build account logo" width="180"/>
      </figure>

      <h4>How to use Account related features:</h4>
      <section class="paragraph">
        <p>
          - First, purchase the account with the features you would like,
          HERE!<br />
          - Folders: Click on ‘New Folder’ in the left menu bar, name your
          folder and click Create.<br />
          - Move a file to a folder: ‘checkbox’ the media you want to move, and
          ‘Double Click’ on the folder you want to move it to.<br />
          - Delete a file or folder: ‘checkbox’ the file or folder you want to
          delete, and click on the red Delete button on the bottom left menu
          bar.<br />
          - ** If you delete a folder with files in it, those files just move to
          the ‘no folder’ section, they are NOT deleted.<br />
          - ‘View All’ allows you to see all free uploads ever made to
          nostr.build, excluding paid accounts and profile pictures. All images
          must be legal and align to nostr.build Terms of Service.<br />
          - If you purchased a Creator account, you can toggle your media to be
          publically available on the nostr.build Creators page, HERE.<br />
          - BTCPay Server Account:<br />
        </p>

        <figure class="img_container double_img">
          <img src="/assets/about/createfolder.png" alt="create a folder" class="about_img" />
          <img src="/assets/about/movedelete.png" alt="move and delete" class="about_img" />
        </figure>
      </section>

      <h3>Contacts</h3>
      <figure class="img_container double_img">
        <a href="https://snort.social/p/npub1nxy4qpqnld6kmpphjykvx2lqwvxmuxluddwjamm4nc29ds3elyzsm5avr7"><img src="/assets/about/nostrbuild.png" alt="nostr.build" class="about_img img_square"/></a>
        <img src="community.png" alt="community" class="about_img img_square" />
        <a href="https://snort.social/p/npub137c5pd8gmhhe0njtsgwjgunc5xjr2vmzvglkgqs5sjeh972gqqxqjak37w"><img src="/assets/about/fishcake.png" alt="fishcake" class="about_img img_square" /></a>
      </figure>
    </main>

    <?= include $_SERVER['DOCUMENT_ROOT']?>
    <script src="/scripts/index.js"></script>
    <br>
  </body>
</html>
