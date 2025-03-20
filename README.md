# WordPress WebP & AVIF Image Optimizer

A high-performance WordPress plugin for converting and optimizing your images to WebP and AVIF formats.

## Features

- **Automatic Conversion**: Automatically convert uploaded images to WebP and AVIF formats
- **Manual Conversion**: Manually convert individual images from the media library
- **Bulk Conversion**: Convert your entire media library in the background
- **Format Detection**: Serve the appropriate format based on browser support
- **Performance Optimization**: High-performance image processing with minimal server impact
- **Detailed Statistics**: Track space savings and conversion rates
- **Flexible Settings**: Customize quality, conversion methods, and more

## Requirements

- WordPress 5.3+
- PHP 8.1+
- GD library with WebP/AVIF support, ImageMagick, or command-line tools

## Installation

1. Upload the `wp-image-optimizer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Settings > WebP & AVIF' to configure the plugin

## Development

### Setup Development Environment

1. Clone this repository
2. Install Composer dependencies: `composer install`
3. Install Git hooks for code quality checks: `./bin/install-git-hooks.sh`

### Code Quality & Security Checks

Different levels of code checking are available:

1. **Basic checks** (required before commits):
   ```
   composer lint
   ```
   Ensures PHP code doesn't contain syntax errors.

2. **Standard checks** (recommended during development):
   ```
   composer check
   ```
   Runs syntax checks, security checks and static analysis with relaxed rules.
   
3. **Strict checks** (same rules as CI):
   ```
   composer check-strict
   ```
   Runs all checks including strict coding standards.

### Fixing Code Style Issues

Fix automatically fixable coding standards issues with:

```
composer fix
```

Or use the comprehensive fixing script:

```
./bin/fix-code-style.sh
```

### Pre-commit Hooks

The pre-commit hook verifies PHP syntax and shows warnings for style issues but doesn't block commits. Install it with:

```
./bin/install-git-hooks.sh
```

## Usage

### Automatic Conversion

By default, the plugin will automatically convert newly uploaded JPEG and PNG images to WebP and AVIF formats. You can disable this in the settings.

### Manual Conversion

You can manually convert images from the media library by:

1. Clicking on an image in the media library
2. Clicking the "Convert Now" button in the WebP & AVIF section

### Bulk Conversion

To convert all your existing images:

1. Go to 'Settings > WebP & AVIF > Bulk Convert'
2. Click the "Start Bulk Conversion" button
3. Wait for the conversion to complete

### Format Detection

The plugin automatically detects browser support and serves the appropriate format (WebP, AVIF, or the original image).

## Advanced Configuration

### Quality Settings

You can adjust the quality of WebP and AVIF images in the settings. Higher values result in better image quality but larger file sizes.

### Conversion Methods

The plugin supports multiple conversion methods:

- **GD Library**: Fast, built-in PHP image processing
- **ImageMagick**: More advanced image processing
- **Command Line Tools**: Highest quality but requires server access

### Performance Tuning

You can adjust batch size and processing delay to balance conversion speed with server load.

## Troubleshooting

### Images Not Converting

- Make sure your server has the necessary libraries (GD with WebP/AVIF support or ImageMagick)
- Check that the images are in a supported format (JPEG or PNG)
- Verify that you have write permissions for the upload directory

### High Server Load

- Reduce the batch size in the performance settings
- Increase the processing delay between batches
- Consider converting images manually or during off-peak hours

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Developed by Your Name
- Uses the WebP and AVIF libraries
