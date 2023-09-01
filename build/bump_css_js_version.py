#!/usr/bin/env python3
import os
import re
import argparse


def update_css(m):
    prefix = m.group(1)
    href = m.group(2)
    version = m.group(4)
    quote = m.group(5)

    if "http:" in href or "https:" in href:
        return m.group(0)  # return the entire match as is

    if version:
        new_version = int(version) + 1
    else:
        new_version = 1
    return f'{prefix}{href}?v={new_version}{quote}'


def update_js(m):
    prefix = m.group(1)
    src = m.group(2)
    version = m.group(4)
    quote = m.group(5)

    if "http:" in src or "https:" in src:
        return m.group(0)  # return the entire match as is

    if version:
        new_version = int(version) + 1
    else:
        new_version = 1
    return f'{prefix}{src}?v={new_version}{quote}'


def update_version(file_path):
    with open(file_path, 'r+') as f:
        content = f.read()
        f.seek(0)

        # Update CSS references
        updated_content = re.sub(r'(<link [^>]*?href=["\'])([^"\']*\.css)(\?v=([0-9]+))?(["\'])',
                                 update_css, content)

        # Update JS references
        updated_content = re.sub(r'(<script [^>]*?src=["\'])([^"\']*\.js)(\?v=([0-9]+))?(["\'])',
                                 update_js, updated_content)

        f.write(updated_content)
        f.truncate()


def find_and_update_files(start_path='.', skip_folders=[]):
    for root, _, filenames in os.walk(start_path):
        # Skip folders that are in skip_folders list
        if any(skip in root for skip in skip_folders):
            continue

        for filename in filenames:
            if filename.endswith('.php'):
                full_path = os.path.join(root, filename)
                update_version(full_path)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(
        description='Update CSS and JS version numbers in PHP files.')
    parser.add_argument('--start', default='.',
                        help='Starting directory for the search.')
    parser.add_argument(
        '--skip', nargs='*', default=['vendor', 'libs', 'scripts'], help='List of folders to skip.')
    args = parser.parse_args()
    find_and_update_files(args.start, args.skip)
