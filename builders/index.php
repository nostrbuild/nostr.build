<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="robots" content="noindex">
	<meta name="keywords" content="nostr, damus image uploader, image link, snort.social, astril.ninja, image, uploader, media upload, damus pictures, video uploader,nostr repository ">
	<meta name="description" content="Image, video and media uploader for nostr, damus, astral.ninja, snort.social, and most all nostr clients. Upload any kind of media and get a link to post, or use our iOS app to automatically uppload images straight from your keyboard.">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="stylesheet" href="/styles/index.css?v=3" />
	<link rel="stylesheet" href="/styles/header.css?v=4" />
	<link rel="stylesheet" href="/styles/builders.css?v=3" />
	<link rel="icon" href="/assets/01.png">

	<title>nostr.build - builders and devs</title>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>

	<main>
		<h1>Builders</h1>
		<div class="builders_container">
			<a href="https://nostrudel.ninja/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">hzrd149</figcaption>
					</div>
					<img src="https://nostr.build/i/p/nostr.build_d8a82acc6ac86b2e167ab2dbfb3a2eafb01979801ad68ec827d7693a6e76b316.png" alt="hzrd149 image" />
				</figure>
			</a>
			<a href="https://damus.io/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Vanessa</figcaption>
					</div>
					<img src="https://image.nostr.build/aa6a75a57c3e6da7fe83f9114ebe661bae6eb379a21d2e1f057d1f6e297e966a.jpg" alt="Vanessa image" />
				</figure>
			</a>
			<a href="https://bitcoinfixesthis.org" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Don't ₿elieve the Hype</figcaption>
					</div>
					<img src="https://i.nostr.build/am9Q.jpg" alt="Dont_₿elieve_the_Hype image" />
				</figure>
			</a>
			<a href="https://www.btcsessions.ca/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">BTC Sessions</figcaption>
					</div>
					<img src="https://static.wixstatic.com/media/f33f9f_e6084386861743ffa347bc29eecf565f~mv2.gif" alt="BTCSessions image" />
				</figure>
			</a>
			<a href="https://zeusln.app" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">EVAN</figcaption>
					</div>
					<img src="https://zeusln.app/nostr/evan.jpg" alt="EVAN image" />
				</figure>
			</a>
			<a href="https://coracle.social" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">hodlbod</figcaption>
					</div>
					<img src="https://us-southeast-1.linodeobjects.com/dufflepud/uploads/b2a7ef93-fa12-469b-bf3d-0f2654cab346.jpg" alt="hodlbod image" />
				</figure>
			</a>
			<a href="https://primal.net" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Miljan</figcaption>
					</div>
					<img src="https://m.primal.net/HGGp.png" alt="miljan image" />
				</figure>
			</a>
			<a href="https://jeffg.fyi/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">JeffG</figcaption>
					</div>
					<img src="https://m.primal.net/HIVN.jpg" alt="JeffG image" />
				</figure>
			</a>
			<a href="https://nostrplebs.com" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Semisol</figcaption>
					</div>
					<img src="https://i.nostrimg.com/prank-enthusiast-willingly.gif" alt="semisol image" />
				</figure>
			</a>
			<a href="https://github.com/SeedSigner/seedsigner" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">SeedSigner</figcaption>
					</div>
					<img src="https://nostr.build/i/221.gif" alt="SeedSigner image" />
				</figure>
			</a>
			<a href="https://nostrcheck.me/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Quentin</figcaption>
					</div>
					<img src="https://nostrcheck.me/media/quentin/avatar.webp?v=555" alt="Quentin image" />
				</figure>
			</a>
			<a href="https://nostr.report" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Marce</figcaption>
					</div>
					<img src="https://image.nostr.build/845f66f76c32fd0bda088f362d9f5c9810f88ad640309aed7697de247784c59e.jpg" alt="Marce image" />
				</figure>
			</a>
			<a href="https://nostr.world" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">McShane</figcaption>
					</div>
					<img src="https://image.nostr.build/90964f9bf9accefc53fde4112692c8560755fbf0e15fd2030df6b11f1fe6655b.jpg" alt="McShane image" />
				</figure>
			</a>
			<a href="https://fiatjaf.com" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">fiatjaf</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/fiatjaf.png" alt="fiatjaf image" />
				</figure>
			</a>
			<a href="https://coinkite.com" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">NVK</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/nvk.png" alt="nvk image" />
				</figure>
			</a>
			<a href="https://iris.to/sirius" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Martti Malmi</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/martti.png" alt="Martti image" />
				</figure>
			</a>
			<a href="https://snort.social/kieran" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Kieran</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/kieran.png" alt="kieran image" />
				</figure>
			</a>
			<a href="https://damus.io/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Will</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/will.png" alt="will image" />
				</figure>
			</a>
			<a href="https://damus.io/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">elsat</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/elsat.png" alt="elsat image" />
				</figure>
			</a>
			<a href="https://damus.io/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Swift</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/swift.png" alt="swift image" />
				</figure>
			</a>
			<a href="https://vitorpamplona.com" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Vitor</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/vitor.png" alt="vitor image" />
				</figure>
			</a>
			<a href="https://nostrgram.co/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">jleger2023</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/jleger.gif" alt="jleger2023 image" />
				</figure>
			</a>
			<a href="https://orangepill.dev/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">EzoFox</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/ezo.png" alt="ezo image" />
				</figure>
			</a>
			<a href="https://uselessshit.co/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">pitiunited</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/pit.gif" alt="useless image" />
				</figure>
			</a>
			<a href="https://nostrplebs.com" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Derek Ross</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/derek.png" alt="derek image" />
				</figure>
			</a>
			<a href="https://github.com/michaelhall923" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Henry</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/henry.png" alt="henry image" />
				</figure>
			</a>
			<a href="https://nostr.build/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">nostr.build</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/nostrbuild.png" alt="nostrbuild image" />
				</figure>
			</a>
			<a href="https://eden.nostr.land" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Cameri</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/cameri.png" alt="cameri image" />
				</figure>
			</a>
			<a href="https://nostr.build" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Fishcake</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/fishcake.png" alt="fishcake image" />
				</figure>
			</a>
			<a href="https://github.com/ng5jr/nostr.build" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Ro₿erto</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/roberto.png" alt="roberto image" />
				</figure>
			</a>
			<a href="https://github.com/ng5jr/nostr.build" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">nahuelg5</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/nahuelg5.png" alt="nahuelg5 image" />
				</figure>
			</a>
			<a href="https://github.com/ng5jr/nostr.build" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Samsamskies</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/samsamskies.png" alt="roberto image" />
				</figure>
			</a>
			<a href="https://walletscrutiny.com/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">WalletScrutiny</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/wallet.png" alt="walletscrutiny image" />
				</figure>
			</a>
			<a href="https://nostr.info/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Giszmo</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/giszmo.png" alt="Giszmo image" />
				</figure>
			</a>
			<a href="https://dergigi.com/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Gigi</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/gigi.png" alt="gigi image" />
				</figure>
			</a>
			<a href="https://nostr.report/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">The Nostr Report</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/nostrreport.gif" alt="nostrreport image" />
				</figure>
			</a>
			<a href="https://pablof7z.com/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">PABLOF7z</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/pablo.png" alt="pablof7z image" />
				</figure>
			</a>
			<a href="https://linktr.ee/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">iefan</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/iefan.png" alt="iefan image" />
				</figure>
			</a>
			<a href="https://habla.news/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">verbiricha</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/verbiricha.png" alt="verbiricha image" />
				</figure>
			</a>
			<a href="https://nostrBadges.com/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Jason</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/jason.png" alt="jason image" />
				</figure>
			</a>
			<a href="https://nostrland.com" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Karnage</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/karnage.gif" alt="karnage image" />
				</figure>
			</a>
			<a href="https://nodeless.io/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">utxo</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/utxo.gif" alt="utxo image" />
				</figure>
			</a>
			<a href="https://iris.to/rabble" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">rabble</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/rabble.png" alt="rabble image" />
				</figure>
			</a>
			<a href="https://getcurrent.io" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">StarBuilder</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/starbuilder.png" alt="starbuilder image" />
				</figure>
			</a>
			<a href="https://app.getcurrent.io" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">egge</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/egge.png" alt="egge image" />
				</figure>
			</a>
			<a href="https://btcpayserver.org/" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Rockstar</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/rockstar.png" alt="rockstar image" />
				</figure>
			</a>
			<a href="https://lightning.store" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">Lightning Store</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/lnstore.gif" alt="lnstore image" />
				</figure>
			</a>
			<a href="/assets/greenskull" target="_blank">
				<figure class="builder_card">
					<div class="card_header">
						<figcaption class="card_title">green skull</figcaption>
					</div>
					<img src="https://cdn.nostr.build/assets/builders/greenskull.png" alt="green skull image" />
				</figure>
			</a>
		</div>
	</main>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
	<script src="/scripts/index.js?v=2"></script>
</body>

</html>
