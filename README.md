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
