<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
// Instantiate the permissions class
$perm = new Permission();

$ppic = (isset($_SESSION["ppic"]) && !empty($_SESSION["ppic"])) ? $_SESSION["ppic"] : "https://nostr.build/assets/temp_ppic.png";

$svg_logo = <<<SVG
<svg width="119" height="42" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:v="https://vecta.io/nano"><linearGradient id="A" gradientUnits="userSpaceOnUse" x1="40.62" y1="21.291" x2="120.582" y2="32.671"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M43.79 18.97c.65-.76 1.46-1.14 2.42-1.15.52-.01.98.07 1.38.25.4.17.72.4.97.69.3.34.5.75.61 1.21.11.45.17.96.17 1.52v4.26h-2.18v-4.09a3.27 3.27 0 0 0-.09-.78 1.63 1.63 0 0 0-.28-.65c-.13-.19-.32-.33-.54-.41-.23-.09-.48-.14-.74-.13-.35.01-.64.08-.89.22-.23.13-.41.31-.54.53s-.22.46-.26.71c-.04.26-.06.52-.06.78v3.83H41.6V18h2.07l.12.97z" fill="url(#A)"/><linearGradient id="B" gradientUnits="userSpaceOnUse" x1="40.814" y1="19.863" x2="121.096" y2="31.288"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M54.76 25.87c-1.31 0-2.32-.35-3.02-1.06-.71-.72-1.06-1.71-1.06-2.97 0-.61.09-1.16.28-1.65a3.71 3.71 0 0 1 .8-1.28c.35-.36.79-.63 1.27-.8.51-.19 1.09-.28 1.73-.28.63 0 1.2.09 1.7.28.51.18.94.44 1.29.8.36.35.63.78.81 1.28.19.49.29 1.04.29 1.65 0 1.28-.36 2.27-1.07 2.97-.7.7-1.71 1.06-3.02 1.06h0zm0-6.19c-.66 0-1.16.21-1.47.62s-.48.93-.48 1.55c0 .63.16 1.15.48 1.56s.81.62 1.47.62 1.15-.21 1.46-.62.46-.93.46-1.56c0-.62-.15-1.13-.46-1.55-.31-.41-.8-.62-1.46-.62h0z" fill="url(#B)"/><linearGradient id="C" gradientUnits="userSpaceOnUse" x1="41.039" y1="18.675" x2="121.159" y2="30.077"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M63.22 19.49c-.15 0-.3.01-.44.03-.13.02-.25.05-.37.1a.58.58 0 0 0-.28.21.5.5 0 0 0-.09.35c0 .15.08.3.2.4.13.1.32.18.55.24l.75.18.81.16.78.21a3.21 3.21 0 0 1 .67.29c.27.16.49.38.66.66s.26.63.25 1.05c0 .4-.07.75-.21 1.05-.13.28-.32.53-.55.74-.3.26-.67.44-1.12.56a5.68 5.68 0 0 1-1.36.16 6.19 6.19 0 0 1-1.47-.16c-.44-.11-.85-.3-1.21-.57a2.66 2.66 0 0 1-.67-.75 2.58 2.58 0 0 1-.31-1.09h2.18c.06.33.23.56.51.69.29.13.62.19 1.01.19l.35-.01c.12-.02.24-.05.35-.09.11-.05.2-.12.28-.21s.12-.2.12-.31c.01-.26-.08-.44-.28-.54-.18-.11-.39-.18-.6-.22l-.69-.15-.74-.15-.71-.18c-.23-.08-.44-.18-.64-.31-.3-.18-.56-.42-.75-.71s-.28-.68-.25-1.16c.02-.42.13-.78.32-1.08a2.34 2.34 0 0 1 .75-.72c.32-.19.66-.32 1.03-.4.39-.08.78-.12 1.18-.12.44 0 .85.04 1.24.13.36.08.71.22 1.01.43a2.19 2.19 0 0 1 .71.77c.18.31.29.7.31 1.15h-2.09c-.04-.32-.17-.54-.4-.65-.21-.12-.48-.17-.79-.17z" fill="url(#C)"/><linearGradient id="D" gradientUnits="userSpaceOnUse" x1="41.464" y1="16.651" x2="121.265" y2="28.008"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M70.64 19.8v3.14c0 .67.35 1 1.06 1h.8v1.81h-1.01c-1.07.04-1.85-.16-2.31-.59s-.69-1.08-.69-1.96v-3.4h-1.23v-1.81h1.23v-2.11h2.16v2.11h1.93v1.81h-1.94z" fill="url(#D)"/><linearGradient id="E" gradientUnits="userSpaceOnUse" x1="41.315" y1="16.836" x2="121.532" y2="28.251"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M75.85 18.96c.33-.37.67-.65 1.04-.84.38-.19.83-.28 1.35-.28l.38.01.34.04v1.91l-.72.01c-.24 0-.46.02-.67.06a2.7 2.7 0 0 0-.63.19c-.19.08-.37.2-.52.35-.24.26-.38.53-.44.82a5.96 5.96 0 0 0-.08 1v3.49h-2.16v-7.76h1.99l.12 1h0z" fill="url(#E)"/><linearGradient id="F" gradientUnits="userSpaceOnUse" x1="40.975" y1="18.835" x2="121.257" y2="30.261"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M81.37 23.3c.39 0 .71.11.95.34.25.23.37.54.37.96 0 .41-.12.73-.37.94-.25.22-.56.32-.95.32a1.49 1.49 0 0 1-.97-.32c-.25-.22-.37-.53-.37-.94s.12-.73.37-.96a1.45 1.45 0 0 1 .97-.34z" fill="url(#F)"/><linearGradient id="G" gradientUnits="userSpaceOnUse" x1="41.823" y1="14.322" x2="121.825" y2="25.707"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M86.56 18.97c.32-.37.73-.66 1.2-.84.46-.19.94-.3 1.44-.31a4.08 4.08 0 0 1 1.59.29c.49.2.9.53 1.23 1 .27.34.46.75.58 1.22.12.48.19.97.18 1.46 0 .64-.08 1.22-.25 1.75a3.03 3.03 0 0 1-.8 1.34c-.32.33-.71.58-1.15.74-.43.15-.88.23-1.33.24-.5 0-1-.08-1.47-.25-.45-.18-.86-.48-1.23-.9v1.03H84.4V15.32h2.16v3.65h0zm0 2.92c.01.63.19 1.14.55 1.53.37.39.87.59 1.52.6.35.01.64-.04.89-.15s.44-.26.6-.46c.16-.21.29-.44.37-.71a2.69 2.69 0 0 0 .14-.87c0-.3-.05-.59-.14-.85a2.03 2.03 0 0 0-.38-.69c-.16-.2-.37-.35-.61-.46-.25-.11-.54-.16-.89-.16-.33 0-.62.06-.89.18a1.74 1.74 0 0 0-.64.49 2.19 2.19 0 0 0-.38.71 2.39 2.39 0 0 0-.14.84z" fill="url(#G)"/><linearGradient id="H" gradientUnits="userSpaceOnUse" x1="41.892" y1="13.723" x2="121.966" y2="25.119"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M97.88 24.02c.59 0 1.01-.15 1.26-.44.25-.3.36-.74.34-1.3v-4.3h2.16v4.36c0 .54-.06 1-.17 1.38a2.7 2.7 0 0 1-.54 1.05c-.19.24-.41.42-.64.54a3.76 3.76 0 0 1-.74.32 4.21 4.21 0 0 1-.83.16c-.28.04-.56.06-.84.06-.63 0-1.21-.08-1.73-.25a2.85 2.85 0 0 1-1.3-.84c-.26-.31-.45-.66-.55-1.05-.1-.38-.15-.84-.15-1.38v-4.36h2.16v4.3c-.01.56.11.99.37 1.28.25.31.65.46 1.2.47h0z" fill="url(#H)"/><linearGradient id="I" gradientUnits="userSpaceOnUse" x1="42.994" y1="11.374" x2="122.13" y2="22.636"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M104.52 14.38c.4 0 .72.11.97.34s.37.53.37.9c0 .39-.12.7-.37.93-.25.22-.57.32-.97.32a1.49 1.49 0 0 1-.97-.32c-.25-.23-.37-.54-.37-.93 0-.37.12-.67.37-.9.26-.23.58-.34.97-.34zm1.09 11.37h-2.16v-7.76h2.16v7.76z" fill="url(#I)"/><linearGradient id="J" gradientUnits="userSpaceOnUse" x1="43.152" y1="11.231" x2="122.2" y2="22.481"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M109.6,25.75h-2.16V15.32h2.16V25.75z" fill="url(#J)"/><linearGradient id="K" gradientUnits="userSpaceOnUse" x1="42.419" y1="9.999" x2="122.549" y2="21.403"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M117.25 24.88c-.31.32-.68.57-1.1.74-.4.15-.88.23-1.46.25-.53 0-1.02-.08-1.47-.24-.44-.16-.84-.41-1.17-.74a3.35 3.35 0 0 1-.83-1.37c-.15-.52-.23-1.05-.23-1.59 0-1.27.37-2.28 1.1-3.05.33-.33.74-.59 1.23-.78s1.05-.28 1.67-.28c.47 0 .89.07 1.26.21.38.13.7.32.95.57v-3.28h2.18v10.43h-2.01l-.12-.87zm-2.05-.86c.65-.02 1.15-.23 1.49-.63.35-.41.52-.92.52-1.52 0-.65-.17-1.17-.52-1.58-.35-.4-.85-.61-1.52-.62-.34 0-.63.05-.89.16a1.74 1.74 0 0 0-.63.47c-.16.2-.29.43-.37.69-.08.27-.13.56-.12.84a3.02 3.02 0 0 0 .12.88c.08.26.2.48.37.68a1.75 1.75 0 0 0 .64.46c.27.12.57.17.91.17z" fill="url(#K)"/><path d="M20 .39L.59 10.66 20 20.93l19.41-10.27L20 .39z" fill="#6f4099"/><path d="M19.97 2.09l-5.36 2.84v11.44L20 19.22l5.39-2.85v-5.7l5.39-2.85c0-.01-10.81-5.73-10.81-5.73zm-3.34 6.75c-.62 0-1.12-.46-1.12-1.03s.5-1.03 1.12-1.03 1.12.46 1.12 1.03-.5 1.03-1.12 1.03zm7.86 1.34l-4.53-2.36 4.53-2.4 4.49 2.38-4.49 2.38h0z" fill="#fff"/><linearGradient id="L" gradientUnits="userSpaceOnUse" x1="10.117" y1="10.946" x2="10.117" y2="41.749"><stop offset="0" stop-color="#9a4098"/><stop offset="1" stop-color="#3b1e56"/><stop offset="1" stop-color="#231714"/></linearGradient><path d="M.41 10.95v20.54l19.41 10.27V21.21L.41 10.95z" fill="url(#L)"/><path d="M17.3 37.76l-3.59-1.91v-.95L6.52 23.49l-.89-.47v8.56l-2.7-1.43V14.94l3.59 1.9v.95l7.19 11.41.9.47v-8.55l2.69 1.42z" fill="#fff"/><path d="M20.18 21.21v20.54l19.41-10.27V10.95L20.18 21.21h0z" fill="#3b1e56"/><path d="M37.07 17.79l-2.7-1.43-11.67 6.18v15.21l12.58-6.65 1.8-2.85v-3.8l-1.8-.95 1.8-2.85-.01-2.86h0zm-2.7 10.46l-1.35 2.14-7.64 4.04v-4.75l7.64-4.04 1.35.71v1.9h0zm0-6.66l-1.35 2.14-7.64 4.04v-4.75l7.64-4.04 1.35.71v1.9h0z" fill="#fff"/></svg>
SVG;
$svg_builder_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.862 4.195c.26-.26.682-.26.943 0l3.333 3.334c.26.26.26.682 0 .943l-3.333 3.333a.667.667 0 0 1-.943-.943L13.724 8l-2.862-2.862a.667.667 0 0 1 0-.943Z" fill="url(#paint0_linear_502_4159)"/><path fill-rule="evenodd" clip-rule="evenodd" d="M5.138 4.195c.26.26.26.683 0 .943L2.276 8l2.862 2.862a.667.667 0 1 1-.943.943L.862 8.472a.667.667 0 0 1 0-.943l3.333-3.334c.26-.26.683-.26.943 0Z" fill="url(#paint1_linear_502_4159)"/><path fill-rule="evenodd" clip-rule="evenodd" d="M9.478 1.35c.36.08.586.435.506.795l-2.667 12a.667.667 0 1 1-1.301-.29l2.667-12a.667.667 0 0 1 .795-.506Z" fill="url(#paint2_linear_502_4159)"/><defs><linearGradient id="paint0_linear_502_4159" x1="13" y1="4" x2="13" y2="12" gradientUnits="userSpaceOnUse"><stop stop-color="#2EDF95"/><stop offset="1" stop-color="#07847C"/></linearGradient><linearGradient id="paint1_linear_502_4159" x1="3" y1="4" x2="3" y2="12" gradientUnits="userSpaceOnUse"><stop stop-color="#2EDF95"/><stop offset="1" stop-color="#07847C"/></linearGradient><linearGradient id="paint2_linear_502_4159" x1="8" y1="1.333" x2="8" y2="14.667" gradientUnits="userSpaceOnUse"><stop stop-color="#2EDF95"/><stop offset="1" stop-color="#07847C"/></linearGradient></defs></svg>
SVG;
$svg_creator_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_502_4163)"><path opacity=".12" d="M1.333 8A6.667 6.667 0 0 0 8 14.667a2 2 0 0 0 2-2v-.334c0-.31 0-.464.017-.594a2 2 0 0 1 1.722-1.722c.13-.017.285-.017.594-.017h.334a2 2 0 0 0 2-2A6.667 6.667 0 0 0 1.333 8Z" fill="url(#paint0_linear_502_4163)"/><path d="M1.333 8A6.667 6.667 0 0 0 8 14.667a2 2 0 0 0 2-2v-.334c0-.31 0-.464.017-.594a2 2 0 0 1 1.722-1.722c.13-.017.285-.017.594-.017h.334a2 2 0 0 0 2-2A6.667 6.667 0 0 0 1.333 8Z" stroke="url(#paint1_linear_502_4163)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.667 8.667a.667.667 0 1 0 0-1.334.667.667 0 0 0 0 1.334Z" stroke="url(#paint2_linear_502_4163)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.667 6a.667.667 0 1 0 0-1.333.667.667 0 0 0 0 1.333Z" stroke="url(#paint3_linear_502_4163)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.667 5.333a.667.667 0 1 0 0-1.333.667.667 0 0 0 0 1.333Z" stroke="url(#paint4_linear_502_4163)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/></g><defs><linearGradient id="paint0_linear_502_4163" x1="1.333" y1="1.333" x2="16.987" y2="5.151" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><linearGradient id="paint1_linear_502_4163" x1="1.333" y1="1.333" x2="16.987" y2="5.151" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><linearGradient id="paint2_linear_502_4163" x1="4" y1="7.333" x2="5.565" y2="7.715" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><linearGradient id="paint3_linear_502_4163" x1="10" y1="4.667" x2="11.565" y2="5.048" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><linearGradient id="paint4_linear_502_4163" x1="6" y1="4" x2="7.565" y2="4.382" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><clipPath id="clip0_502_4163"><path fill="#fff" d="M0 0h16v16H0z"/></clipPath></defs></svg>
SVG;
$svg_freeview_icon = <<<SVG
<svg width="16" height="14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.848 12.819 7.246 8.42c.264-.264.396-.396.548-.446a.666.666 0 0 1 .412 0c.152.05.284.182.548.446l4.369 4.368M9.333 9l1.913-1.912c.264-.264.396-.396.548-.446a.666.666 0 0 1 .412 0c.152.05.284.182.548.446L14.667 9m-8-4A1.333 1.333 0 1 1 4 5a1.333 1.333 0 0 1 2.667 0Zm-2.134 8h6.934c1.12 0 1.68 0 2.108-.218a2 2 0 0 0 .874-.874c.218-.428.218-.988.218-2.108V4.2c0-1.12 0-1.68-.218-2.108a2 2 0 0 0-.874-.874C13.147 1 12.587 1 11.467 1H4.533c-1.12 0-1.68 0-2.108.218a2 2 0 0 0-.874.874c-.218.428-.218.988-.218 2.108v5.6c0 1.12 0 1.68.218 2.108a2 2 0 0 0 .874.874c.428.218.988.218 2.108.218Z" stroke="url(#paint0_linear_502_4699)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><defs><linearGradient id="paint0_linear_502_4699" x1="1" y1="1" x2="15" y2="13" gradientUnits="userSpaceOnUse"><stop stop-color="#EC46FB"/><stop offset="1" stop-color="#5E44FF"/></linearGradient></defs></svg>
SVG;
$svg_memestr_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_502_4658)"><path d="M5.333 9.333s1 1.334 2.667 1.334c1.667 0 2.667-1.334 2.667-1.334M10 6h.007M6 6h.007m8.66 2A6.667 6.667 0 1 1 1.333 8a6.667 6.667 0 0 1 13.334 0Zm-4.334-2a.333.333 0 1 1-.666 0 .333.333 0 0 1 .666 0Zm-4 0a.333.333 0 1 1-.666 0 .333.333 0 0 1 .666 0Z" stroke="url(#paint0_linear_502_4658)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/></g><defs><linearGradient id="paint0_linear_502_4658" x1="1" y1="1.333" x2="16.225" y2="13.079" gradientUnits="userSpaceOnUse"><stop stop-color="#FCFF6D"/><stop offset="1" stop-color="#C8FFA7"/></linearGradient><clipPath id="clip0_502_4658"><path fill="#fff" d="M0 0h16v16H0z"/></clipPath></defs></svg>
SVG;
$svg_about_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.333 7.333h-3.2c-.746 0-1.12 0-1.405.146-.25.127-.455.331-.583.582C2 8.347 2 8.72 2 9.467V14m12 0V4.133c0-.746 0-1.12-.145-1.405a1.333 1.333 0 0 0-.583-.583C12.987 2 12.613 2 11.867 2h-2.4c-.747 0-1.12 0-1.406.145-.25.128-.455.332-.582.583-.146.285-.146.659-.146 1.405V14m7.334 0H1.333m8.334-9.333h2m-2 2.666h2m-2 2.667h2" stroke="url(#paint0_linear_502_4738)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><defs><linearGradient id="paint0_linear_502_4738" x1="1" y1="2" x2="15" y2="14" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".4"/></linearGradient></defs></svg>
SVG;
$svg_edu_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.333 12.667v-2H4.667a2 2 0 0 0-2 2m3.2 2H11.2c.747 0 1.12 0 1.405-.146.251-.128.455-.332.583-.582.145-.286.145-.659.145-1.406V3.467c0-.747 0-1.12-.145-1.406a1.333 1.333 0 0 0-.583-.582c-.285-.146-.658-.146-1.405-.146H5.867c-1.12 0-1.68 0-2.108.218a2 2 0 0 0-.874.874c-.218.428-.218.988-.218 2.108v6.934c0 1.12 0 1.68.218 2.108a2 2 0 0 0 .874.874c.427.218.988.218 2.108.218Z" stroke="url(#paint0_linear_502_4618)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><defs><linearGradient id="paint0_linear_502_4618" x1="2.4" y1="1.333" x2="16.47" y2="10.016" gradientUnits="userSpaceOnUse"><stop stop-color="#46B5F3"/><stop offset="1" stop-color="#283CF0"/></linearGradient></defs></svg>
SVG;
$svg_menu_icon = <<<SVG
<svg width="24" height="24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M2 12a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1ZM2 6a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1ZM2 18a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1Z" fill="#D0BED8"/></svg>
SVG;
?>

<nav class="navigation_header">
  <a href="https://nostr.build" class="">
    <?= $svg_logo ?>
  </a>
  <div class="center_buttons">
    <a href="/builders" class="nav_button">
      <?= $svg_builder_icon ?>
      Builders
    </a>
    <a href="/creators" class="nav_button">
      <?= $svg_creator_icon ?>
      Creators
    </a>
    <?php if ($perm->isGuest()) : ?>
      <a href="/freeview" class="nav_button">
        <?= $svg_freeview_icon ?>
        Free View
      </a>
    <?php else : ?>
      <a href="/viewall" class="nav_button">
        <?= $svg_freeview_icon ?>
        View All
      </a>
    <?php endif; ?>
    <a href="/edu" class="nav_button">
      <?= $svg_edu_icon ?>
      Edu
    </a>
    <a href="/about" class="nav_button">
      <?= $svg_about_icon ?>
      About
    </a>
  </div>
  <?php if ($perm->isGuest()) : ?>
    <a href="/login" class="nav_button login_button">
      Signup/Login
    </a>
  <?php else : ?>
    <a href="/account" class="nav_button login_button">
      Account
      <img src="<?= $ppic ?>" alt="user image" style="width:33px;height:33px;border-radius:50%;">
    </a>
  <?php endif; ?>
  <button class="menu_button">
    <?= $svg_menu_icon ?>
  </button>
</nav>
</header>
<nav>
  <div class="menu">
    <a href="/builders" class="nav_button">
      <span>
        <?= $svg_builder_icon ?>
      </span>
      Builders
    </a>
    <a href="/creators" class="nav_button">
      <span>
        <?= $svg_creator_icon ?>
      </span>
      Creators
    </a>
    <?php if ($perm->isGuest()) : ?>
      <a href="/freeview" class="nav_button">
        <span>
          <?= $svg_freeview_icon ?>
        </span>
        Free View
      </a>
    <?php else : ?>
      <a href="/viewall" class="nav_button">
        <span>
          <?= $svg_freeview_icon ?>
        </span>
        View All
      </a>
    <?php endif; ?>
    <a href="https://shop.nostr.build" class="nav_button">
      <span>
        <?= $svg_memestr_icon ?>
      </span>
      Shop
    </a>
    <a href="/edu" class="nav_button">
      <span>
        <?= $svg_edu_icon ?>
      </span>
      Edu
    </a>
    <a href="/about" class="nav_button">
      <span>
        <?= $svg_about_icon ?>
      </span>
      About
    </a>
    <?php if ($perm->isGuest()) : ?>
      <a href="/login" class="nav_button login_button login_desktop">
        <span>
          Signup/Login
        </span>
      </a>
    <?php else : ?>
      <a href="/account" class="nav_button login_button login_desktop">
        <span style="display: flex; align-items: center;">
          <span style="margin-right: 10px;">Account</span>
          <img src="<?= $ppic ?>" alt="user image" style="width:33px;height:33px;border-radius:50%">
        </span>
      </a>
    <?php endif; ?>
  </div>
  <?php if ($perm->isGuest()) { ?>
    <a class="ref_link" style="font-size: large" href="https://nostr.build/signup/new/"><img src="https://i.nostr.build/0Q82.png" style="width:220px;" alt="Premium Accounts"></a>
    <?php } ?>
</nav>
