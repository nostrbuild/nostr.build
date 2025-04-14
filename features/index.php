<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
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
  <meta name="keywords" content="features, media hosting, cloud, decentralized, primal, nostr, damus image uploader, image link, snort.social, astril.ninja, image, uploader, media upload, damus pictures, video uploader,nostr repository " />
  <meta name="description" content="A complete and detailed list of nostr.build features with images supporting the nostr social media platform. nostr.build is a cloud media hosting service that is paid in bitcoin and lightning and hosts user images to share." />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="stylesheet" href="/styles/index.css?v=290253d31f2fde0932483cb54581766b" />
  <link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />
  <link rel="stylesheet" href="/styles/tos.css?v=bf70ea4e016c3323424fbe67747e22a5" />
  <link rel="icon" href="https://cdn.nostr.build/assets/01.png" />

  <style>
    .center {
        display: block;
        margin-left: auto;
        margin-right: auto;
    }
    .text1 { 
      width: 50%; 
      min-width: 20em; 
      float: left; 
    }
    .image1 { 
      width: 50%; 
      min-width: 20em; 
    }

  </style>

  <title>nostr.build - Features</title>
</head>

<body>
  <header class="header">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
  </header>

  <main class="about_page">
    <h2>nostr.build</h2>
    <h1>Features</h1>
    <h3>Free Services</h3>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">No KYC:</span> Know Your Customer (KYC), is a common requirement for many products and services requiring the customer's name, 
      address, and other personal information. This is automatic when purchasing anything with a Credit Card. Nostr.build only 
      accepts Bitcoin and Sats, and the only requirement for identification is a nostr npub, which is also non KYC.</p></td>
    </tr>
    </table></br>
   <hr width="90%" size="1" color="9d83a2" class="center"></br>

   <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">Never Ads:</span> These days it seems like every service and social media platform has ads.. They even 
      introduced it to paid teirs of Netflix, Prime, and Disney+. Ads arn't just insignifigant commercials you ignore, with your personal profile
      built by these huge companies, they target you with focused ads, Tweets, and posts designed specifically to change your mind about something, 
      not just buying a product! Ads are very dangerous and a threat to free thinking. Nostr.build will never use ads!</p></td>
    </tr>
    </table></br>
   <hr width="90%" size="1" color="9d83a2" class="center"></br>

   <section class="paragraph">
    <p><span style="color:white">Blossom Support for Nostr:</span>  Blossom has become a standard protocol for hosting media on nostr. It makes it 
	  very easy for any nostr app to connect to any Blossom server, <a class="ref_link" href="https://v.nostr.build/RL8J5JBA9vPE0cki.mp4" target="_blank">check out the video here!</a>
	  This not only greatly helps with overall interoperability, it also makes it easier to have multiple servers, mirror servers, and for users 
	  to host their own media server. Blossom is a free service, but it also works with nostr.build Accounts. Because Blossom is based on Blob technology, 
	  it is limited to 100MB uploads. We recommend using native NIP96 nostr.build integration if your app supports it, ex. Damus, Amethyst, noStrudel, Snort, Iris, Coracle, and YakiHonne.</p>
      <img src="https://cdn.nostr.build/assets/images/blossom01.png" alt="Media Management Portal" width="250" style="width:90%"></a>
   </section>
    </br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">Supported Media Files up to 100MB:</span> Anyone using Nostr can upload the media of their choice, up to 21MB per file.
      We support many different media types including .jpg, .png, .gif, .mov, .mp4, .mp3, and .wav. We compress JPEGs, PNGs and GIFs, 
      but currently perform only light processing of video to allow quick preview. If your video is playing weird, no audio or not playing at all, 
      it is likely your video format or how it was transcoded.</p></td>
    </tr>
    </table>
   <img src="https://cdn.nostr.build/assets/images/media_supported_free01.png" alt="Supported Media" class="center" width="70%" style="max-width: 400px;"></br>
   <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td style="width:77%">
      <p><span style="color:white">Removes Location-Related Metadata:</span>  Most photographs these days have the location data (GPS coordinates) embedded in the metadata. 
      The data is commonly used to tell users where they were when they took the picture, it can also dox someone if they share an 
      image with location data in it, e.g., their home address. Nostr.build removes this location metadata so as not to reveal your 
      location to everyone you are sharing a picture with. </p>
      <img src="https://cdn.nostr.build/assets/images/remaining_metadata.png" alt="Removes Location Metadata" width="98%" style="max-width: 350px;" class="center"></td>
      <td style="width:35%"><img src="https://cdn.nostr.build/assets/images/image_location01.png" alt="Metadata Locatrion 1" >
    <img src="https://cdn.nostr.build/assets/images/image_location02.png" alt="Metadata Location 2">
    </td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td style="width:100%">
      <p><span style="color:white">Integrated Media Uploading on Nostr Platforms:</span> In the early days of Nostr, if you wanted to share an image or video, 
      you would have to upload it to another site, copy the link it provides, and paste it to your Nostr note. With NIP96 and NIP98 
      nostr.build is now directly integrated into most Nostr apps making it just one click to add your media, like other popular and social media platforms. 
      Currently this is supported on Damus, Amethyst, Nostrudel, Snort, Iris, Coracle, Flycat, and Yakihonne, among others. </p></td>
      <td style="width:80%"> <video src="https://cdn.nostr.build/assets/images/nostr_upload01.mp4" width="150" alt="Free Media Gallery" autoplay loop muted playsinline></video> </td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <section class="paragraph">
      <p>
      <span style="color:white">Free Media Gallery:</span>  Curious what type of images, memes, gifs, and videos are being uploaded to Nostr? Check out the Free Media Gallery 
      to see the most recent uploades to nostr.build. This view only shares the free uploads, you would not be able to see any user account uploads 
      which are kept private unless purposely shared by the account owner. <a class="ref_link" href="https://nostr.build/freeview" target="_blank">https://nostr.build/freeview </a>
      </p>
      <video src="https://cdn.nostr.build/assets/images/freeview01.mp4" style="width:90%" alt="Free Media Gallery" autoplay loop muted playsinline></video>
    </section>
      </br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">Delete Uploaded Media:</span>  If you upload free media to Nostr using a Nostr app, you can go back and delete it yourself with our delete tool. 
        This is most used when people dox themselves, accidentally uploading an image with their name or address. The image needs to be associated to 
        your n-pub and you will need to authenticate before deleting. <a class="ref_link" href="https://nostr.build/delete/" target="_blank">https://nostr.build/delete/</a>
        <img src="https://cdn.nostr.build/assets/images/delete_free01.png" alt="Delete Free Media Uploads" width="98%" style="max-width: 600px;" class="center"></p></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>
  
    <section class="paragraph">
    <p><span style="color:white">CSAM Scanning, Removal and Reporting:</span>  CSAM is not tolerated in any way. Not just CSAM, but any media that exploits a child 
        including AI and cartoons, children in inappropriate positions or clothing, we block the user and report their content and all related 
        information we have on the user to the authorities / NCMEC. We use multiple services when filtering and reporting CSAM including 
        Cloudflare's CSAM filter, Microsoft PhotoDNA, AI models and the NCMEC reporting portal. We do not filter or report anything else that is in 
        compliance with our TOS, only CSAM. <a class="ref_link" href="https://www.missingkids.org/theissues/csam" target="_blank">https://www.missingkids.org/theissues/csam</a>
        </p>
        <img src="https://cdn.nostr.build/assets/images/ncmec01.png" alt="CSAM NCMEC Reporting" style="width:90%">
        </section>
  
  </br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">24/7 Support:</span>  nostr.build has people around the world, monitoring Nostr every day for Nostr and nostr.build related issues. Issues include 
        but are not limited to; media not uploading or displaying, doxed uploads, illegal content removal, developer issues, and feature requests. 
        There are very few services in the world where you can interact directly with the developers and get support and feature requests fulfilled. 
        You can also contact us at <a href="mailto:support@nostr.build">support@nostr.build</a></p></td>
    </tr>
    </table></br></br>

    <h3>Pro Accounts</h3>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">25GB of Private Storage:</span> One of the most common problems with the free service is the file size is capped at 100MB per file. For long videos, 
        this becomes a challenge. With paid accounts, there is no file size limit to what you upload, only the size of the account. </p></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <section class="paragraph">
    <p><span style="color:white">Media Management Portal:</span>  One of the biggest advantages of a nostr.build account is the ability to keep all your media in one place, 
        separate from social media platforms, in your own private portal. You can drag and drop, add, create folders, move to a folder, delete, 
        and rename any of your media, and much more. Check out our nostr.build features overview video to get a better idea of media management 
        in your nostr.build account.</p>
	<iframe src="https://e.nostr.build/v_o7Kcp0r1c0LgRR9G_mp4?t=nb_new_features.mp4&by=nostr.build" style="border: 0; width: 90%; aspect-ratio: 16/10" allow="fullscreen; encrypted-media; picture-in-picture" loading="lazy" title="nb_new_features.mp4"></iframe>
    </section>
    </br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td style="width:58%">
      <p><span style="color:white">AI Studio:</span>  Our latest and one of our coolest features is the all-new AI Studio. AI Studio provides text-to-image generation with multiple 
        popular models to choose from. While other platforms only offer a single text-to-image model (ex. ChatGPT/DallE, Discord/Midjourney, X/Grok2), 
        nostr.build offers multiple models on the same platform. We also offer a complete media management system for your AI creations mentioned above, 
        the other platforms don’t have that. <a class="ref_link" href="https://cdn.nostr.build/assets/images/nb_aistudio_compete01.pdf" target="_blank">Check out our competitive comparison here!</a> For Pro accounts we offer SDXL-Lightning, and Stable Diffusion 1. </p></td>
        <td style="width:30%"><img src="https://cdn.nostr.build/assets/images/aistudio_02.png" alt="Metadata Locatrion 1" width="98%" style="max-width: 180px;" >
        <img src="https://cdn.nostr.build/assets/images/aiimage03.png" alt="Metadata Location 2" width="98%" style="max-width: 120px;">
        </td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">.pdf and .zip File Support:</span>  Another huge request from users was to support the .pdf and .zip file types. This wasn’t as easy as it seems since 
        we have to perform a virus scan on each of these file types before publishing and allowing public access. </p></td>
      <td><img src="https://cdn.nostr.build/assets/images/pdf_zipimages01.png" alt="nostr.build v1"></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">View All Media Gallery:</span>  Curious what type of images, memes, gifs and videos are being uploaded to nostr.build's free service? Check out the Media Gallery 
        to see all 2Million+ uploads ever uploaded to nostr.build. This only shares the free uploads, you would not be able to see any user account uploads 
        which are kept private unless shared. </p></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td style="width:65%">
      <p><span style="color:white">Share Direct to Nostr:</span> Upload or create new media on nostr.build and share it with or without a note straight to Nostr, no other client needed! 
        This is a feature that more tightly integrates nostr.build with Nostr and takes it steps closer to becoming a true Nostr client. </p></td>
      <td style="width:95%"><video src="https://cdn.nostr.build/assets/images/share_to_nostr01.mp4" style="width:95%" alt="Share to Nostr Action" autoplay loop muted playsinline></video>
      <img src="https://cdn.nostr.build/assets/images/sharetonostr01.png" alt="Share to Nostr Button" width="95%"></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">Global Content Delivery Network:</span> Lightning-fast global CDN distribution of your content for faster, easier viewing around the world.
      <img src="https://cdn.nostr.build/assets/images/cdndiagram01.png" alt="nostr.build v1" width="80%" style="max-width: 450px;" class="center"></p></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td style="width:50%">
      <p><span style="color:white">Referral Link:</span> Found in your profile settings, share your referral link and earn ‘Credits’ when someone uses it to purchase an account. 
        Credits can be used for advanced features, account upgrades, and renewals.
        <img src="https://cdn.nostr.build/assets/images/referral01.png" alt="nostr.build v1" width="80%" style="max-width: 450px;" class="center"></p></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td style="width:60%">
      <p><span style="color:white">Viewer and Usage Stats:</span> See the total ‘Unique Views’ and ‘Request Counts’ for all of your media, nicely graphed out, from 1-day to 3-month chart options. 
        Just click the three dots under your media and select the 'Statistics' tab.</p></td>
      <td style="width:50%"><img src="https://cdn.nostr.build/assets/images/stats01.png" alt="nostr.build v1" width="33%" style="max-width: 250px;">
      <img src="https://cdn.nostr.build/assets/images/stats02.png" alt="nostr.build v1" width="65%" style="max-width: 250px;"></td>
    </tr>
    </table></br></br>

    <h3>Creator Accounts</h3>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">50GB of Private Storage:</span>  2x more storage than the Pro account. Ideal for creators, podcasts, and videographers, 
        this gives you the added storage for those larger projects. </p></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">.svg File Support:</span> Scalable Vector Graphics (SVG) files use mathematical formulas to store graphics. 
        This makes them able to be resized without losing quality, very common with designers and content creators. </p></td>
      <td><img src="https://cdn.nostr.build/assets/images/svgicon01.png" alt="nostr.build v1"></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td style="width:58%">
      <p><span style="color:white">AI Studio:</span>  When creating images in AI Studio, Creator accounts have access to all Pro Stable Diffusion models and unlimited use of the Flux.1 model. 
        Flux.1 (schnell) is one of the latest text-to-image models, Flux is a 2 billion parameter rectified flow transformer that excels in graphic detail and correct text spelling and layout. 
        It is the same core model that X(Twitter) uses with Grok2 images.</p></td>
        <td style="width:30%"><img src="https://cdn.nostr.build/assets/images/aistudio_01.png" alt="Metadata Locatrion 1" width="98%" style="max-width: 180px;" >
        <img src="https://cdn.nostr.build/assets/images/aiimage02.png" alt="Metadata Location 2" width="98%" style="max-width: 120px;">
        </td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">Host a Creators Page:</span> Creators have the option to share their media to their Creators page hosted on nostr.build. 
      This makes it easier for people to see, donate, and share. <a class="ref_link" href="https://nostr.build/creators/" target="_blank">Check out all the Creators and their masterpieces here!</a>
		<video src="https://cdn.nostr.build/assets/images/creators11.mp4" width="80%" class="center" alt="Creators Page" autoplay loop muted playsinline></video>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">iDrive E2 backup for all media:</span> We currently store all media on Cloudflare R2 servers. If for whatever reason Cloudflare servers lose your content, 
        we also store a backup on a completely different service provider, iDrive E2 servers.
        <img src="https://cdn.nostr.build/assets/images/idrivee201.png" alt="iDrive Backup" width="80%" style="max-width: 225px;" class="center"></p></td>
    </tr>
    </table></br></br>

    <h3>Advanced Accounts</h3>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">250GB of Private Storage:</span> The largest account size we offer, with 5x the storage of the Creator account. 
        This is plenty of storage for all of your Nostr needs and more.</p></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td>
      <p><span style="color:white">AI Studio Extended Access:</span> Get all the models, all the latest experimental features, and additional Credits with an Advanced account.</p></td>
    </tr>
    </table></br>
    <hr width="90%" size="1" color="9d83a2" class="center"> </br>

    <table style="width:100%">
    <tr>
      <td style="width:80%">
      <p><span style="color:white">NIP-05 @nostr.build:</span> Do you need premium NIP-05 identification? Choose any name you want and have your own @nostr.build official profile.</p></td>
      <td><img src="https://cdn.nostr.build/assets/images/nip05auth01.png" alt="nb NIP05" width="98%" style="max-width: 200px;"></td>
    </tr>
    </table></br></br>

    <h3>Roadmap</h3>

    <p><span style="color:white">Free Account:</span> This would be just enough to get someone started with nostr.build’s media management features and slightly larger media size uploads.
    </p></br>

    <p><span style="color:white">Lifetime Account:</span> This would be something like all features, all experimental features, 1TB of storage for life, and two free t-Shirts.
    </p></br>

    <p><span style="color:white">Traditional and AI Media Editors:</span>  Combining a powerful, standard media editor Pintura, with modern AI image editing features from stability.ai 
      would allow users to quickly and easily modify their media using multiple tools.
      </br><a class="ref_link" href="https://pqina.nl/pintura/?ref=pqina" target="_blank">https://pqina.nl/pintura/?ref=pqina </a>
      </br><a class="ref_link" href="https://stability.ai/stable-image" target="_blank">https://stability.ai/stable-image </a>
    </p></br>

    <p><span style="color:white">Video Transcoding and Player:</span> nostr.build has never modified video meaning all different formats and sizes are uploaded, 
      none of them optimized for the platform they are being viewed on, and all of them in many different formats. By properly transcoding 
      the media it will be viewable, optimized and faster on all platforms the video is being watched on, Android, iOS, desktops, laptops, etc.
    </p></br>

    <p><span style="color:white">Expandable Storage:</span> The ability to purchsase additional chunks of storage and add it to your existing account.
    </p></br>

    <p><span style="color:white">Primal Support:</span> Integrated support for the nostr Primal app. No more copy/paste, just select nostr.build as your media provider and start uploading direct from Primal.
    </p>

  </main>

  <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
</body>

</html>
