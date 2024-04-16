#!/usr/bin/env python3

import os
import re
import argparse
import hashlib

def calculate_checksum(file_path):
    with open(file_path, 'rb') as f:
        content = f.read()
        checksum = hashlib.md5(content).hexdigest()
    return checksum

def update_version(m, root_folder):
    tag = m.group(1)
    path = m.group(2)

    if "http:" in path or "https:" in path:
        return m.group(0)  # return the entire match as is

    file_path = os.path.join(root_folder, path.lstrip('/'))
    if os.path.isfile(file_path):
        checksum = calculate_checksum(file_path)
        return f'{tag}{path}?v={checksum}"'
    else:
        return m.group(0)  # return the entire match as is if file not found

def process_file(file_path, root_folder):
    with open(file_path, 'r+') as f:
        content = f.read()
        f.seek(0)

        # Update CSS and JS references
        updated_content = re.sub(r'(<(?:link|script)[^>]*(?:href|src)=")([^"]*?\.(?:css|js))(?:\?v=[^"]*)?(")',
                                 lambda m: update_version(m, root_folder), content)

        if updated_content != content:
            print(f'Updated CSS and JS checksums for {file_path}')
            f.write(updated_content)
            f.truncate()

def find_and_update_files(start_path='.', skip_folders=[], root_folder=None):
    if root_folder is None:
        root_folder = start_path

    for root, dirs, filenames in os.walk(start_path):
        dirs[:] = [d for d in dirs if d not in skip_folders]

        for filename in filenames:
            if filename.endswith('.php'):
                full_path = os.path.join(root, filename)
                process_file(full_path, root_folder)

if __name__ == '__main__':
    parser = argparse.ArgumentParser(
        description='Update CSS and JS version numbers in PHP files using file checksums.')
    parser.add_argument('--start', default='..',
                        help='Starting directory for the search.')
    parser.add_argument('--skip', nargs='*', default=['vendor', 'libs', 'scripts', 'build'],
                        help='List of folders to skip.')
    parser.add_argument('--root', default='..',
                        help='Root folder from which to find target files.')
    args = parser.parse_args()

    find_and_update_files(args.start, args.skip, args.root)