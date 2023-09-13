<?php

function handle_pagination($total, $page, $shown, $url, $top = true)
{
  if ($total < 0 || $page < 0 || $shown <= 0 || empty($url)) {
    return '...';
  }
  $pages = ceil($total / $shown);
  $range_start = max(1, $page - 2);
  $range_end = min($pages, $page + 4);
?>
  <?php if ($top) : ?>
    <nav class="flex items-center justify-between border-t border-gray-200 px-4 sm:px-2 w-screen my-1">
    <?php else : ?>
      <nav class="flex items-center justify-between border-b border-gray-200 px-4 sm:px-2 w-screen my-1">
      <?php endif; ?>
      <div class="-mt-px flex w-0 flex-1">
        <?php if ($page > 0) : ?>
          <?php if ($top) : ?>
            <a href="<?= $url . ($page - 1); ?>" class="inline-flex items-center border-t-2 border-transparent pr-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
            <?php else : ?>
              <a href="<?= $url . ($page - 1); ?>" class="inline-flex items-center border-b-2 border-transparent pr-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
              <?php endif; ?>
              <svg class="mr-3 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a.75.75 0 01-.75.75H4.66l2.1 1.95a.75.75 0 11-1.02 1.1l-3.5-3.25a.75.75 0 010-1.1l3.5-3.25a.75.75 0 111.02 1.1l-2.1 1.95h12.59A.75.75 0 0118 10z" clip-rule="evenodd" />
              </svg>
              Previous
              </a>
            <?php endif; ?>
      </div>
      <div class="hidden md:-mt-px md:flex">
        <?php if ($range_start > 1) : ?>
          <?php if ($top) : ?>
            <span class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500">...</span>
          <?php else : ?>
            <span class="inline-flex items-center border-b-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500">...</span>
          <?php endif; ?>
        <?php endif; ?>
        <?php foreach (range($range_start, $range_end) as $value) : ?>
          <?php if ($value == $page) : ?>
            <?php if ($top) : ?>
              <a href="#" class="inline-flex items-center border-t-2 border-indigo-500 px-4 pt-4 text-sm font-medium text-indigo-600" aria-current="page"><?= $value ?></a>
            <?php else : ?>
              <a href="#" class="inline-flex items-center border-b-2 border-indigo-500 px-4 pt-4 text-sm font-medium text-indigo-600" aria-current="page"><?= $value ?></a>
            <?php endif; ?>
          <?php else : ?>
            <?php if ($top) : ?>
              <a href="<?= $url . $value ?>" class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700"><?= $value ?></a>
            <?php else : ?>
              <a href="<?= $url . $value ?>" class="inline-flex items-center border-b-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700"><?= $value ?></a>
            <?php endif; ?>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($range_end < $pages) : ?>
          <?php if ($top) : ?>
            <span class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500">...</span>
          <?php else : ?>
            <span class="inline-flex items-center border-b-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500">...</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="-mt-px flex w-0 flex-1 justify-end">
        <?php if ($page < $pages - 1) : ?>
          <?php if ($top) : ?>
            <a href="<?= $url . ($page + 1); ?>" class="inline-flex items-center border-t-2 border-transparent pl-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
            <?php else : ?>
              <a href="<?= $url . ($page + 1); ?>" class="inline-flex items-center border-b-2 border-transparent pl-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
              <?php endif; ?>
              Next
              <svg class="ml-3 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M2 10a.75.75 0 01.75-.75h12.59l-2.1-1.95a.75.75 0 111.02-1.1l3.5 3.25a.75.75 0 010 1.1l-3.5 3.25a.75.75 0 11-1.02-1.1l2.1-1.95H2.75A.75.75 0 012 10z" clip-rule="evenodd" />
              </svg>
              </a>
            <?php endif; ?>
      </div>
      </nav>
    <?php
  }
