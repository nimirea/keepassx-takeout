# KeePassX Takeout

A PHP command line utility for converting from the KeePassX XML format to other password manager formats.

## Requirements

- PHP 5.5.14+ (untested on previous versions, but will probably work on 5.0.1+)
- Unix-like filesystem

## Usage

1. Export your KeePassX database to XML format
2. Run `php kpx-takeout.php -- output_format (flags) /path/to/xmlfile.xml (/path/to/output.ext)` in your shell of choice
  - Paths can be either absolute or relative
  - The output path can be either a file or a directory, in which case the output file will be named  `[format]-import.[ext]`
  - If no output path is specified, the current directory will be used; this is the *directory from which the script is run*, which may not be the directory where  `kpx-takeout.php` or your input file is located
  - Flag options:
    * `-o` will **o**verwrite an existing file at the output location, if it exists. If a directory is specified as the output location and this flag is not set, the script will create a new file with a datestamp appended.
    * `-v` will **v**erbosely output information to the console. When this flag is unset, the program will only output errors.
4. Import the output file into your password manager of choice
5. **Delete both the unencrypted export and import files after you're done with them!!!**

## Supported Output Formats & Notes

- LastPass CSV (`lastpass`)
  * LastPass does not support nested folders. As of right now, neither does this script.
- PasswordSafe TXT (`passwordsafe`)
  * PasswordSafe does not support group names with periods in them, so the script will automatically replace any periods with hyphens.

## License

This software is licensed under the GPL v2.0 License.
