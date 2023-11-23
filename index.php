<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UploadsData.class.php';

// Globals
global $link;

// Instantiate permissions class
$perm = new Permission();

// Fetch statistics
$uploadsData = new UploadsData($link);
$stats = $uploadsData->getStats();

$total_files = $stats['total_files'];
$total_size_gb = round($stats['total_size'] / (1024 * 1024 * 1024), 2); // Convert bytes to GB

header("Expires: Thu, 19 Nov 1981 08:52:00 GMT"); //Date in the past
header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0"); //HTTP/1.1
header("Pragma: no-cache");

// THIS SVG EXTRACTS ARE A TEMP THING, WE WILL CLEAN THIS UP LATER
// TODO: Clean up this SVG extract
$svg_sharing_container = <<<SVG
<svg width="36" height="34" viewBox="0 0 36 34" fill="none" xmlns="http://www.w3.org/2000/svg">
<path fill-rule="evenodd" clip-rule="evenodd" d="M34.8452 0.821524C35.496 1.47241 35.496 2.52767 34.8452 3.17856L28.1785 9.84522C27.5277 10.4961 26.4723 10.4961 25.8215 9.84522L22.4882 6.51189C21.8373 5.86101 21.8373 4.80574 22.4882 4.15486C23.139 3.50399 24.1943 3.50399 24.8452 4.15486L27 6.30969L32.4882 0.821524C33.139 0.170657 34.1943 0.170657 34.8452 0.821524Z" fill="url(#paint0_linear_35_374)" />
<path fill-rule="evenodd" clip-rule="evenodd" d="M6.16669 11.1666C6.16669 8.40521 8.40527 6.16663 11.1667 6.16663C13.9282 6.16663 16.1667 8.40521 16.1667 11.1666C16.1667 13.9281 13.9282 16.1666 11.1667 16.1666C8.40527 16.1666 6.16669 13.9281 6.16669 11.1666Z" fill="url(#paint1_linear_35_374)" />
<path fill-rule="evenodd" clip-rule="evenodd" d="M17.8333 0.333375H9.93118C8.58958 0.333358 7.48229 0.333341 6.58031 0.407024C5.64348 0.483574 4.78229 0.647841 3.97341 1.06001C2.71899 1.69916 1.69913 2.71902 1.05998 3.97344C0.647811 4.78232 0.483544 5.64351 0.406994 6.58034C0.333311 7.48232 0.333327 8.58957 0.333344 9.93119V24.0689C0.333327 25.4105 0.333311 26.5177 0.406994 27.4197C0.483544 28.3565 0.647811 29.2177 1.05998 30.0267C1.69913 31.281 2.71899 32.3009 3.97341 32.94C4.78229 33.3522 5.64348 33.5165 6.58031 33.593C6.87261 33.6169 7.18648 33.633 7.52291 33.644C7.87176 33.6672 8.28901 33.667 8.71908 33.667C14.2572 33.667 19.7952 33.6667 25.3333 33.6667C25.4108 33.6667 25.4873 33.6667 25.5627 33.6667C26.8883 33.6675 27.8727 33.668 28.7255 33.4395C31.026 32.823 32.823 31.026 33.4395 28.7255C33.7317 27.635 33.667 26.4675 33.6667 25.3492C33.6697 25.0337 33.6668 24.718 33.6668 24.4025C33.6677 23.621 33.6685 22.9324 33.4872 22.2745C33.3282 21.697 33.0665 21.1529 32.715 20.6679C32.3145 20.1154 31.7763 19.6857 31.1655 19.1982L26.4472 15.4235C26.164 15.197 25.884 14.973 25.6298 14.7995C25.3472 14.6065 24.9992 14.406 24.5637 14.2944C23.9515 14.1374 23.3072 14.1577 22.7062 14.353C22.2785 14.4919 21.9438 14.7139 21.6738 14.9242C21.431 15.1135 21.1658 15.3547 20.8975 15.5987L6.71744 28.4897C6.36164 28.813 6.01769 29.1257 5.76484 29.3992C5.66346 29.509 5.50946 29.6795 5.36549 29.905C4.79379 29.5835 4.32873 29.0997 4.02999 28.5134C3.89718 28.2527 3.78896 27.879 3.72926 27.1484C3.66798 26.3982 3.66668 25.4277 3.66668 24V10C3.66668 8.57241 3.66798 7.60192 3.72926 6.85177C3.78896 6.12107 3.89718 5.74741 4.02999 5.48674C4.34956 4.85954 4.85951 4.34959 5.48671 4.03002C5.74738 3.89721 6.12104 3.78899 6.85174 3.72929C7.60189 3.66801 8.57238 3.66671 10 3.66671H17.8333C18.7538 3.66671 19.5 2.92052 19.5 2.00004C19.5 1.07957 18.7538 0.333375 17.8333 0.333375Z" fill="url(#paint2_linear_35_374)" />
<defs>
	<linearGradient id="paint0_linear_35_374" x1="28.6667" y1="0.333374" x2="28.6667" y2="10.3334" gradientUnits="userSpaceOnUse">
		<stop stop-color="#2EDF95" />
		<stop offset="1" stop-color="#07847C" />
	</linearGradient>
	<linearGradient id="paint1_linear_35_374" x1="11.1667" y1="6.16663" x2="11.1667" y2="16.1666" gradientUnits="userSpaceOnUse">
		<stop stop-color="#2EDF95" />
		<stop offset="1" stop-color="#07847C" />
	</linearGradient>
	<linearGradient id="paint2_linear_35_374" x1="17.0032" y1="0.333374" x2="17.0032" y2="33.667" gradientUnits="userSpaceOnUse">
		<stop stop-color="#2EDF95" />
		<stop offset="1" stop-color="#07847C" />
	</linearGradient>
</defs>
</svg>
SVG;

$svg_image_address = <<<SVG
<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
<path opacity="0.12" d="M2 6.46671C2 5.71997 2 5.3466 2.14533 5.06139C2.27315 4.8105 2.47713 4.60653 2.72801 4.4787C3.01323 4.33337 3.3866 4.33337 4.13333 4.33337H9.53333C10.2801 4.33337 10.6535 4.33337 10.9387 4.4787C11.1895 4.60653 11.3935 4.8105 11.5213 5.06139C11.6667 5.3466 11.6667 5.71997 11.6667 6.46671V11.8667C11.6667 12.6134 11.6667 12.9868 11.5213 13.272C11.3935 13.5229 11.1895 13.7269 10.9387 13.8547C10.6535 14 10.2801 14 9.53333 14H4.13333C3.3866 14 3.01323 14 2.72801 13.8547C2.47713 13.7269 2.27315 13.5229 2.14533 13.272C2 12.9868 2 12.6134 2 11.8667V6.46671Z" fill="url(#paint0_linear_220_216)" />
<path d="M5 2H9.73333C11.2268 2 11.9735 2 12.544 2.29065C13.0457 2.54631 13.4537 2.95426 13.7093 3.45603C14 4.02646 14 4.77319 14 6.26667V11M4.13333 14H9.53333C10.2801 14 10.6535 14 10.9387 13.8547C11.1895 13.7269 11.3935 13.5229 11.5213 13.272C11.6667 12.9868 11.6667 12.6134 11.6667 11.8667V6.46667C11.6667 5.71993 11.6667 5.34656 11.5213 5.06135C11.3935 4.81046 11.1895 4.60649 10.9387 4.47866C10.6535 4.33333 10.2801 4.33333 9.53333 4.33333H4.13333C3.3866 4.33333 3.01323 4.33333 2.72801 4.47866C2.47713 4.60649 2.27315 4.81046 2.14533 5.06135C2 5.34656 2 5.71993 2 6.46667V11.8667C2 12.6134 2 12.9868 2.14533 13.272C2.27315 13.5229 2.47713 13.7269 2.72801 13.8547C3.01323 14 3.3866 14 4.13333 14Z" stroke="url(#paint1_linear_220_216)" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round" />
<defs>
	<linearGradient id="paint0_linear_220_216" x1="6.83333" y1="4.33337" x2="6.83333" y2="14" gradientUnits="userSpaceOnUse">
		<stop stop-color="#292556" />
		<stop offset="1" stop-color="#120A24" />
	</linearGradient>
	<linearGradient id="paint1_linear_220_216" x1="8" y1="2" x2="8" y2="14" gradientUnits="userSpaceOnUse">
		<stop stop-color="#292556" />
		<stop offset="1" stop-color="#120A24" />
	</linearGradient>
</defs>
</svg>
SVG;

$svg_drag_area_header = <<<SVG
<svg class="image_svg" width="73" height="76" viewBox="0 0 73 76" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_160_807)">
	<g opacity="0.12">
		<path d="M26.4633 37.1009C28.878 36.1997 30.1048 33.5117 29.2036 31.097C28.3024 28.6824 25.6143 27.4556 23.1997 28.3568C20.7851 29.258 19.5582 31.9461 20.4595 34.3607C21.3607 36.7754 24.0487 38.0021 26.4633 37.1009Z" fill="url(#paint0_linear_160_807)" />
		<path d="M36.8393 38.2093L25.8784 62.2249L60.8549 49.1703L36.8393 38.2093Z" fill="url(#paint1_linear_160_807)" />
		<path d="M60.3314 34.4223L50.4463 29.9106C49.274 29.3756 47.8899 29.8922 47.3548 31.0644L42.8432 40.9495L60.424 48.9736C60.7935 48.7229 61.1065 48.4574 61.3794 48.1601C62.3787 47.0708 63.0075 45.6932 63.1756 44.2245C63.3668 42.5547 62.6814 40.7185 61.3105 37.0455L60.3314 34.4223Z" fill="url(#paint2_linear_160_807)" />
	</g>
	<path d="M26.2511 61.4081L35.2891 41.6057C35.8317 40.417 36.1031 39.8223 36.5417 39.474C36.9274 39.1674 37.4004 38.9909 37.8927 38.9698C38.4522 38.9455 39.0469 39.2169 40.2357 39.7595L59.9059 48.7372M42.8431 40.9496L46.7735 32.3382C47.316 31.1494 47.5875 30.5547 48.0261 30.2065C48.4117 29.8999 48.8848 29.7233 49.377 29.7023C49.9366 29.678 50.5312 29.9494 51.72 30.492L60.3314 34.4223M29.2035 31.097C30.1048 33.5117 28.8779 36.1997 26.4633 37.1009C24.0487 38.0022 21.3607 36.7754 20.4594 34.3607C19.5582 31.9461 20.785 29.258 23.1997 28.3568C25.6143 27.4556 28.3023 28.6824 29.2035 31.097ZM31.9992 59.9403L54.7339 51.4548C58.4069 50.0839 60.2432 49.3986 61.3793 48.1601C62.3787 47.0708 63.0074 45.6932 63.1756 44.2245C63.3667 42.5547 62.6814 40.7185 61.3105 37.0455L54.4568 18.6829C53.086 15.01 52.4005 13.1735 51.1621 12.0375C50.0728 11.0382 48.6952 10.4093 47.2265 10.2412C45.5567 10.05 43.7205 10.7354 40.0475 12.1063L17.3128 20.5918C13.6399 21.9626 11.8035 22.6481 10.6674 23.8864C9.66808 24.9758 9.03927 26.3535 8.8711 27.8221C8.67991 29.4918 9.36534 31.3282 10.7362 35.0011L17.5899 53.3638C18.9608 57.0367 19.6461 58.873 20.8846 60.0092C21.9739 61.0085 23.3516 61.6373 24.8202 61.8054C26.4899 61.9966 28.3263 61.3112 31.9992 59.9403V59.9403Z" stroke="url(#paint3_linear_160_807)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
</g>
<rect x="18" y="23" width="54" height="50" rx="9" fill="#27224E" />
<g opacity="0.12">
	<path d="M35.6667 45.6667C38.244 45.6667 40.3333 43.5774 40.3333 41C40.3333 38.4227 38.244 36.3333 35.6667 36.3333C33.0893 36.3333 31 38.4227 31 41C31 43.5774 33.0893 45.6667 35.6667 45.6667Z" fill="url(#paint4_linear_160_807)" />
	<path d="M45.0002 50.3333L26.3335 69H63.6668L45.0002 50.3333Z" fill="url(#paint5_linear_160_807)" />
	<path d="M68.3337 55L60.6502 47.3166C59.7391 46.4054 58.2616 46.4054 57.3504 47.3166L49.667 55L63.3322 68.6652C63.7659 68.5595 64.1521 68.4202 64.5117 68.237C65.8288 67.5659 66.8996 66.4952 67.5707 65.178C68.3337 63.6805 68.3337 61.7205 68.3337 57.8V55Z" fill="url(#paint6_linear_160_807)" />
</g>
<path d="M26.968 68.3651L42.3599 52.9733C43.2839 52.0493 43.7461 51.587 44.2788 51.4141C44.7474 51.2618 45.2523 51.2618 45.7208 51.4141C46.2535 51.587 46.7158 52.0493 47.6398 52.9733L62.9289 68.2624M49.6665 55L56.3599 48.3066C57.2839 47.3826 57.7461 46.9204 58.2788 46.7475C58.7474 46.5951 59.2523 46.5951 59.7208 46.7475C60.2535 46.9204 60.7158 47.3826 61.6398 48.3066L68.3332 55M40.3332 41C40.3332 43.5774 38.2438 45.6667 35.6665 45.6667C33.0892 45.6667 30.9998 43.5774 30.9998 41C30.9998 38.4227 33.0892 36.3333 35.6665 36.3333C38.2438 36.3333 40.3332 38.4227 40.3332 41ZM32.8665 69H57.1332C61.0536 69 63.0136 69 64.5112 68.237C65.8283 67.5659 66.8991 66.4952 67.5702 65.178C68.3332 63.6805 68.3332 61.7205 68.3332 57.8V38.2C68.3332 34.2796 68.3332 32.3194 67.5702 30.8221C66.8991 29.5049 65.8283 28.4341 64.5112 27.763C63.0136 27 61.0536 27 57.1332 27H32.8665C28.9461 27 26.9859 27 25.4886 27.763C24.1714 28.4341 23.1006 29.5049 22.4295 30.8221C21.6665 32.3194 21.6665 34.2796 21.6665 38.2V57.8C21.6665 61.7205 21.6665 63.6805 22.4295 65.178C23.1006 66.4952 24.1714 67.5659 25.4886 68.237C26.9859 69 28.9461 69 32.8665 69V69Z" stroke="url(#paint7_linear_160_807)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
<defs>
	<linearGradient id="paint0_linear_160_807" x1="24.1456" y1="30.8733" x2="26.0078" y2="37.2338" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<linearGradient id="paint1_linear_160_807" x1="38.7428" y1="43.238" x2="42.9328" y2="55.8409" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<linearGradient id="paint2_linear_160_807" x1="50.8924" y1="35.7885" x2="55.0883" y2="50.8433" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<linearGradient id="paint3_linear_160_807" x1="32.9396" y1="27.6723" x2="41.53" y2="56.2476" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<linearGradient id="paint4_linear_160_807" x1="35.6729" y1="39.0217" x2="35.1934" y2="45.6319" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<linearGradient id="paint5_linear_160_807" x1="45.025" y1="55.7102" x2="44.5436" y2="68.9826" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<linearGradient id="paint6_linear_160_807" x1="59.0128" y1="52.9793" x2="57.6795" y2="68.551" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<linearGradient id="paint7_linear_160_807" x1="45.0309" y1="39.0978" x2="43.0869" y2="68.8731" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<clipPath id="clip0_160_807">
		<rect width="56" height="56" fill="white" transform="translate(0 19.5819) rotate(-20.4675)" />
	</clipPath>
</defs>
</svg>
SVG;

$svg_upload_button = <<<SVG
<svg width="16" height="15" viewBox="0 0 16 15" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10.6666 7.99998L7.99998 5.33331M7.99998 5.33331L5.33331 7.99998M7.99998 5.33331V11.4666C7.99998 12.3938 7.99998 12.8574 8.36698 13.3764C8.61085 13.7212 9.31291 14.1468 9.73145 14.2036C10.3614 14.2889 10.6006 14.1641 11.079 13.9146C13.2111 12.8024 14.6666 10.5712 14.6666 7.99998C14.6666 4.31808 11.6818 1.33331 7.99998 1.33331C4.31808 1.33331 1.33331 4.31808 1.33331 7.99998C1.33331 10.4676 2.67397 12.622 4.66665 13.7748" stroke="url(#paint0_linear_198_91)" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round" />
<defs>
	<linearGradient id="paint0_linear_198_91" x1="7.99998" y1="1.33331" x2="7.99998" y2="14.2303" gradientUnits="userSpaceOnUse">
		<stop stop-color="#292556" />
		<stop offset="1" stop-color="#120A24" />
	</linearGradient>
</defs>
</svg>
SVG;

$svg_import_icon = <<<SVG
<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M12.7071 18.3639L11.2929 19.7781C9.34024 21.7308 6.17441 21.7308 4.22179 19.7781C2.26917 17.8255 2.26917 14.6597 4.22179 12.7071L5.636 11.2929M18.364 12.7071L19.7782 11.2929C21.7308 9.34024 21.7308 6.17441 19.7782 4.22179C17.8255 2.26917 14.6597 2.26917 12.7071 4.22179L11.2929 5.636M8.5 15.4999L15.5 8.49994" stroke="#97DEB3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
</svg>
SVG;

$svg_drag_area_loading = <<<SVG
<svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
<path opacity="0.12" d="M25.3333 8C14.2876 8 5.33331 16.9543 5.33331 28C5.33331 33.5096 7.56118 38.4989 11.1652 42.116L21.3333 42.6667L32 32L42.6666 42.7264V44.0072L53.3333 44.6475C56.5906 41.9573 58.6666 37.8877 58.6666 33.3333C58.6666 25.2331 52.1002 18.6667 44 18.6667C43.6784 18.6667 43.3592 18.677 43.0426 18.6974C39.6946 12.3369 33.0205 8 25.3333 8Z" fill="url(#paint0_linear_40_1026)" />
<path d="M21.3333 42.6667L32 32M32 32L42.6666 42.6667M32 32V56M53.3333 44.6475C56.5906 41.9573 58.6666 37.8877 58.6666 33.3333C58.6666 25.2331 52.1002 18.6667 44 18.6667C43.4173 18.6667 42.8722 18.3627 42.5762 17.8606C39.0989 11.9596 32.6784 8 25.3333 8C14.2876 8 5.33331 16.9543 5.33331 28C5.33331 33.5096 7.56118 38.4989 11.1652 42.116" stroke="url(#paint1_linear_40_1026)" stroke-width="5.33333" stroke-linecap="round" stroke-linejoin="round" />
<defs>
	<linearGradient id="paint0_linear_40_1026" x1="32.0355" y1="18.5561" x2="30.7381" y2="44.5828" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
	<linearGradient id="paint1_linear_40_1026" x1="32.0355" y1="21.8261" x2="29.8138" y2="55.855" gradientUnits="userSpaceOnUse">
		<stop stop-color="white" />
		<stop offset="1" stop-color="#884EA4" />
	</linearGradient>
</defs>
</svg>
SVG;
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

	<link rel="stylesheet" href="/styles/index.css?v=5" />
	<link rel="stylesheet" href="/styles/header.css?v=6" />
	<link rel="icon" href="/assets/01.png">

	<script defer src="/scripts/index.js?v=6"></script>

	<title>nostr.build media uploader</title>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>

	<main>
		<div class="title_container">
			<h1>nostr media uploader</h1>
			<p>removes metadata, free, nostr focused</p>
			<div class="info_cards">
				<div class="info"><span><?= $total_size_gb ?></span>GB used</div>
				<div class="info"><span><?= number_format($total_files) ?></span> total uploads</div>
			</div>
		</div>

		<div class="drag-area">

			<div class="drag-area_loading hidden_element">
				<?= $svg_drag_area_loading ?>
				<div class="upload_title">
					<h2>Uploading media</h2>
					<p>Wait a little bit, your media will be ready soon.</p>
				</div>
				<div class="loading_bar">
					<div class="loading_state"></div>
				</div>
				<p class="loading_info">Uploading <span></span>%</p>
				<button class="cancel_upload">Cancel</button>
			</div>

			<div class="drag-area_sharing hidden_element">
				<div class="sharing_container">
					<?= $svg_sharing_container ?>
					<div class="sharing_info">
						<p>Your <span>image</span> is ready for sharing</p>
						<a href="">https://nostr.build/i/3856.png</a>
					</div>
				</div>
				<button class="image_address">
					<?= $svg_image_address ?>
					Copy Image Address
				</button>
			</div>

			<div class="drag-area_header">
				<?= $svg_drag_area_header ?>
				<div id="spinner-container">
					<div class="spinner"></div>
					<div class="spinner-text">Uploading...</div>
				</div>
				<form id="upload-media-form" class="form" action="upload.php" method="post" enctype="multipart/form-data" style="text-align: center">
					<h2 class="drag-area_title">drag and drop your media here</h2>
					<p class="drag-area_subtitle">OR</p>
					<button type="button" class="upload_button">
						<?= $svg_upload_button ?>
						Choose Media
					</button>
					<p class="supported_file">supports: <span>jpg, png, webp, gif, mov, mp4 or mp3</span></p>
					<input id="input_file" class="hidden_input" hidden type="file" accept=".jpeg, .jpg, .png, .gif, .mov, .mp4, .webp, .mp3" name="fileToUpload" id="fileToUpload" />
					<!-- Submit button -->
					<div class="upload_btn_group">
						<input type="submit" value="Upload Media" name="submit" class="import_button media_upload_btn" disabled />
						<input type="submit" value="Upload Profile Pic" name="submit_ppic" class="import_button pfp_upload_btn" disabled />
					</div>
					<!-- /Submit button -->
					<div class="media_container">
						<img src="" alt="" class="uploaded_img">
						<video id="video-player" class="uploaded_video hidden_element" controls=""></video>
						<audio id="audio-player" class="uploaded_audio hidden_element" controls=""></audio>
					</div>
					<div class="preview hidden_element" class="contact">(PREVIEW)</div>
					<div class="import">
						<div class="input_container">
							<div class="import_icon">
								<?= $svg_import_icon ?>
							</div>
							<input autocomplete="off" class="input_url" type="text" name="img_url" id="img_url" placeholder="OR paste image URL to import" /><br />
						</div>
					</div>
				</form>
			</div>

		</div>

		<div class="terms">
			By using nostr.build you agree to our <a href="/tos/"><span>Terms of Service</span></a>
		</div>
	</main>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>

</body>

</html>
