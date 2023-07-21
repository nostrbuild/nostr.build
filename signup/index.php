<!DOCTYPE html>
<html class="h-full antialiased" lang="en">

<head>
  <meta charSet="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Nostr.build Signup</title>
  <link rel="stylesheet" href="/styles/twbuild.css" />
  <script defer src="/scripts/fw/alpinejs.min.js"></script>
  <script defer src="/scripts/fw/htmx.min.js"></script>
</head>

<body class="bg-white">
  <!-- Navbar -->
  <header>
    <nav class="mx-auto flex max-w-7xl items-center justify-between p-6 lg:px-8" aria-label="Global">
      <a href="#" class="-m-1.5 p-1.5">
        <span class="sr-only">Your Company</span>
        <img class="h-8 w-auto" src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600" alt="">
      </a>
      <div class="flex lg:hidden">
        <button type="button" class="-m-2.5 inline-flex items-center justify-center rounded-md p-2.5 text-gray-700">
          <span class="sr-only">Open main menu</span>
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
      </div>
      <div class="hidden lg:flex lg:gap-x-12">
        <a href="#" class="text-sm font-semibold leading-6 text-gray-900">Product</a>
        <a href="#" class="text-sm font-semibold leading-6 text-gray-900">Features</a>
        <a href="#" class="text-sm font-semibold leading-6 text-gray-900">Marketplace</a>
        <a href="#" class="text-sm font-semibold leading-6 text-gray-900">Company</a>

        <a href="#" class="text-sm font-semibold leading-6 text-gray-900">Log in <span aria-hidden="true">&rarr;</span></a>
      </div>
    </nav>
    <!-- Mobile menu, show/hide based on menu open state. -->
    <div class="lg:hidden" role="dialog" aria-modal="true">
      <!-- Background backdrop, show/hide based on slide-over state. -->
      <div class="fixed inset-0 z-50"></div>
      <div class="fixed inset-y-0 right-0 z-50 w-full overflow-y-auto bg-white px-6 py-6 sm:max-w-sm sm:ring-1 sm:ring-gray-900/10">
        <div class="flex items-center justify-between">
          <a href="#" class="-m-1.5 p-1.5">
            <span class="sr-only">Your Company</span>
            <img class="h-8 w-auto" src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600" alt="">
          </a>
          <button type="button" class="-m-2.5 rounded-md p-2.5 text-gray-700">
            <span class="sr-only">Close menu</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="mt-6 flow-root">
          <div class="-my-6 divide-y divide-gray-500/10">
            <div class="space-y-2 py-6">
              <a href="#" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-900 hover:bg-gray-50">Product</a>
              <a href="#" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-900 hover:bg-gray-50">Features</a>
              <a href="#" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-900 hover:bg-gray-50">Marketplace</a>
              <a href="#" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-900 hover:bg-gray-50">Company</a>
            </div>
            <div class="py-6">
              <a href="#" class="-mx-3 block rounded-lg px-3 py-2.5 text-base font-semibold leading-7 text-gray-900 hover:bg-gray-50">Log in</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
  <!-- /Navbar -->
  <main>
    <!-- Pricing section -->
    <div class="mx-auto mt-16 max-w-7xl px-6 sm:mt-32 lg:px-8">
      <div class="mx-auto max-w-4xl text-center">
        <h1 class="text-base font-semibold leading-7 text-indigo-600">Pricing</h1>
        <p class="mt-2 text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">Pricing plans for teams of&nbsp;all&nbsp;sizes</p>
      </div>
      <p class="mx-auto mt-6 max-w-2xl text-center text-lg leading-8 text-gray-600">Choose an affordable plan that’s packed with the best features for engaging your audience, creating customer loyalty, and driving sales.</p>
      <div class="isolate mx-auto mt-10 grid max-w-md grid-cols-1 gap-8 md:max-w-2xl md:grid-cols-2 lg:max-w-4xl xl:mx-0 xl:max-w-none xl:grid-cols-4">
        <div class="rounded-3xl p-8 ring-1 ring-gray-200">
          <h2 id="tier-hobby" class="text-lg font-semibold leading-8 text-gray-900">Hobby</h2>
          <p class="mt-4 text-sm leading-6 text-gray-600">The essentials to provide your best work for clients.</p>
          <p class="mt-6 flex items-baseline gap-x-1">
            <!-- Price, update based on frequency toggle state -->
            <span class="text-4xl font-bold tracking-tight text-gray-900">$15</span>
            <!-- Payment frequency, update based on frequency toggle state -->
            <span class="text-sm font-semibold leading-6 text-gray-600">/month</span>
          </p>
          <a href="#" aria-describedby="tier-hobby" class="mt-6 block rounded-md py-2 px-3 text-center text-sm font-semibold leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 text-indigo-600 ring-1 ring-inset ring-indigo-200 hover:ring-indigo-300">Buy plan</a>
          <ul role="list" class="mt-8 space-y-3 text-sm leading-6 text-gray-600">
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              5 products
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Up to 1,000 subscribers
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Basic analytics
            </li>
          </ul>
        </div>
        <div class="rounded-3xl p-8 ring-1 ring-gray-200">
          <h2 id="tier-freelancer" class="text-lg font-semibold leading-8 text-gray-900">Freelancer</h2>
          <p class="mt-4 text-sm leading-6 text-gray-600">The essentials to provide your best work for clients.</p>
          <p class="mt-6 flex items-baseline gap-x-1">
            <!-- Price, update based on frequency toggle state -->
            <span class="text-4xl font-bold tracking-tight text-gray-900">$30</span>
            <!-- Payment frequency, update based on frequency toggle state -->
            <span class="text-sm font-semibold leading-6 text-gray-600">/month</span>
          </p>
          <a href="#" aria-describedby="tier-freelancer" class="mt-6 block rounded-md py-2 px-3 text-center text-sm font-semibold leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 text-indigo-600 ring-1 ring-inset ring-indigo-200 hover:ring-indigo-300">Buy plan</a>
          <ul role="list" class="mt-8 space-y-3 text-sm leading-6 text-gray-600">
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              5 products
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Up to 1,000 subscribers
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Basic analytics
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              48-hour support response time
            </li>
          </ul>
        </div>
        <div class="rounded-3xl p-8 ring-2 ring-indigo-600">
          <h2 id="tier-startup" class="text-lg font-semibold leading-8 text-indigo-600">Startup</h2>
          <p class="mt-4 text-sm leading-6 text-gray-600">A plan that scales with your rapidly growing business.</p>
          <p class="mt-6 flex items-baseline gap-x-1">
            <!-- Price, update based on frequency toggle state -->
            <span class="text-4xl font-bold tracking-tight text-gray-900">$60</span>
            <!-- Payment frequency, update based on frequency toggle state -->
            <span class="text-sm font-semibold leading-6 text-gray-600">/month</span>
          </p>
          <a href="#" aria-describedby="tier-startup" class="mt-6 block rounded-md py-2 px-3 text-center text-sm font-semibold leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 bg-indigo-600 text-white shadow-sm hover:bg-indigo-500">Buy plan</a>
          <ul role="list" class="mt-8 space-y-3 text-sm leading-6 text-gray-600">
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              25 products
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Up to 10,000 subscribers
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Advanced analytics
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              24-hour support response time
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Marketing automations
            </li>
          </ul>
        </div>
        <div class="rounded-3xl p-8 ring-1 ring-gray-200">
          <h2 id="tier-enterprise" class="text-lg font-semibold leading-8 text-gray-900">Enterprise</h2>
          <p class="mt-4 text-sm leading-6 text-gray-600">Dedicated support and infrastructure for your company.</p>
          <p class="mt-6 flex items-baseline gap-x-1">
            <!-- Price, update based on frequency toggle state -->
            <span class="text-4xl font-bold tracking-tight text-gray-900">$90</span>
            <!-- Payment frequency, update based on frequency toggle state -->
            <span class="text-sm font-semibold leading-6 text-gray-600">/month</span>
          </p>
          <a href="#" aria-describedby="tier-enterprise" class="mt-6 block rounded-md py-2 px-3 text-center text-sm font-semibold leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 text-indigo-600 ring-1 ring-inset ring-indigo-200 hover:ring-indigo-300">Buy plan</a>
          <ul role="list" class="mt-8 space-y-3 text-sm leading-6 text-gray-600">
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Unlimited products
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Unlimited subscribers
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Advanced analytics
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              1-hour, dedicated support response time
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Marketing automations
            </li>
            <li class="flex gap-x-3">
              <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
              </svg>
              Custom reporting tools
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- FAQ section -->
    <div class="mx-auto my-24 max-w-7xl px-6 sm:my-56 lg:px-8">
      <div class="mx-auto max-w-4xl divide-y divide-gray-900/10">
        <h2 class="text-2xl font-bold leading-10 tracking-tight text-gray-900">Frequently asked questions</h2>
        <dl class="mt-10 space-y-6 divide-y divide-gray-900/10">
          <div class="pt-6">
            <dt>
              <!-- Expand/collapse question button -->
              <button type="button" class="flex w-full items-start justify-between text-left text-gray-900" aria-controls="faq-0" aria-expanded="false">
                <span class="text-base font-semibold leading-7">What&#039;s the best thing about Switzerland?</span>
                <span class="ml-6 flex h-7 items-center">
                  <!--
                    Icon when question is collapsed.

                    Item expanded: "hidden", Item collapsed: ""
                  -->
                  <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                  </svg>
                  <!--
                    Icon when question is expanded.

                    Item expanded: "", Item collapsed: "hidden"
                  -->
                  <svg class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6" />
                  </svg>
                </span>
              </button>
            </dt>
            <dd class="mt-2 pr-12" id="faq-0">
              <p class="text-base leading-7 text-gray-600">I don&#039;t know, but the flag is a big plus. Lorem ipsum dolor sit amet consectetur adipisicing elit. Quas cupiditate laboriosam fugiat.</p>
            </dd>
          </div>

          <!-- More questions... -->
        </dl>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-gray-900" aria-labelledby="footer-heading">
    <h2 id="footer-heading" class="sr-only">Footer</h2>
    <div class="mx-auto max-w-7xl px-6 py-16 sm:py-24 lg:px-8 lg:py-32">
      <div class="xl:grid xl:grid-cols-3 xl:gap-8">
        <img class="h-7" src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=500" alt="Company name">
        <div class="mt-16 grid grid-cols-2 gap-8 xl:col-span-2 xl:mt-0">
          <div class="md:grid md:grid-cols-2 md:gap-8">
            <div>
              <h3 class="text-sm font-semibold leading-6 text-white">Solutions</h3>
              <ul role="list" class="mt-6 space-y-4">
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Marketing</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Analytics</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Commerce</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Insights</a>
                </li>
              </ul>
            </div>
            <div class="mt-10 md:mt-0">
              <h3 class="text-sm font-semibold leading-6 text-white">Support</h3>
              <ul role="list" class="mt-6 space-y-4">
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Pricing</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Documentation</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Guides</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">API Status</a>
                </li>
              </ul>
            </div>
          </div>
          <div class="md:grid md:grid-cols-2 md:gap-8">
            <div>
              <h3 class="text-sm font-semibold leading-6 text-white">Company</h3>
              <ul role="list" class="mt-6 space-y-4">
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">About</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Blog</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Jobs</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Press</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Partners</a>
                </li>
              </ul>
            </div>
            <div class="mt-10 md:mt-0">
              <h3 class="text-sm font-semibold leading-6 text-white">Legal</h3>
              <ul role="list" class="mt-6 space-y-4">
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Claim</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Privacy</a>
                </li>
                <li>
                  <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Terms</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </footer>
</body>

</html>