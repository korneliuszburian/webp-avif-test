# WebP & AVIF Image Optimizer for WordPress

![Version](https://img.shields.io/github/v/release/korneliuszburian/webp-avif-test?sort=semver&style=flat-square)
![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue?style=flat-square)
![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D5.8-blue?style=flat-square)
![License](https://img.shields.io/github/license/korneliuszburian/webp-avif-test?style=flat-square)

A high-performance WordPress plugin for automatically converting and optimizing your images to WebP and AVIF formats, delivering smaller file sizes and faster loading times without sacrificing quality.

## 🌟 Features

- **Automatic Conversion**: Automatically convert uploaded images to WebP and AVIF formats
- **Manual Conversion**: Selectively convert individual images from the media library
- **Bulk Processing**: Convert your entire media library in the background with progress tracking
- **Smart Delivery**: Automatically serve the appropriate image format based on browser support
- **Flexible Settings**: Customize quality, conversion methods, and performance parameters
- **Lossless Support**: Option for lossless compression to maintain perfect quality
- **Performance Optimized**: Batch processing with adjustable server load management
- **Clean Architecture**: Built with modern PHP 8.1+ features and design patterns

## 📋 Requirements

- WordPress 5.8+
- PHP 8.1+
- One of the following:
  - PHP GD extension with WebP/AVIF support
  - PHP Imagick extension with WebP/AVIF support
  - Command-line tools: cwebp, avifenc (for best quality)

## 🚀 Installation

### Automatic Updates

This plugin supports automatic updates directly through the WordPress admin panel.

### Manual Installation

1. Download the latest release from the [Releases page](https://github.com/korneliuszburian/webp-avif-test/releases)
2. Upload the zip file via the WordPress admin (Plugins > Add New > Upload Plugin)
3. Activate the plugin
4. Go to Settings > WebP & AVIF to configure options

## ⚙️ Configuration

### General Settings

- **Auto Convert**: Automatically convert images upon upload
- **Enable WebP**: Generate WebP versions of images
- **Enable AVIF**: Generate AVIF versions of images

### Format-Specific Settings

- **Quality**: Adjust the compression quality (1-100)
- **Lossless**: Enable lossless compression (larger files but perfect quality)
- **AVIF Speed**: Balance between encoding speed and compression efficiency

### Performance Settings

- **Batch Size**: Number of images to process in each batch during bulk conversion
- **Processing Delay**: Time between batches to prevent server overload

## 🔧 Usage

### Converting Existing Images

1. Go to Media Library
2. For individual images:
   - Click on an image
   - Look for the "WebP & AVIF" section
   - Click "Convert Now"
3. For bulk conversion:
   - Go to Settings > WebP & AVIF > Bulk Convert
   - Click "Start Bulk Conversion"

### Status Indicators

In the Media Library, each image shows its conversion status:
- 🟢 Green checkmark: Both WebP and AVIF versions available
- 🟠 Orange checkmark: WebP version available
- 🔵 Blue checkmark: AVIF version available
- 🔴 Red X: No optimized versions available

## 🛠 Development

### Prerequisites

- PHP 8.1+
- Composer
- Node.js and npm

### Setup for Development

1. Clone the repository
   ```bash
   git clone https://github.com/korneliuszburian/webp-avif-test.git
   cd webp-avif-test
   ```

2. Install dependencies
   ```bash
   composer install
   npm install
   ```

3. Setup Git hooks
   ```bash
   npm run prepare
   ```

### Development Commands

- **Lint PHP code**: `composer lint`
- **Fix PHP code style**: `composer fix`
- **Full code checks**: `composer check-strict`
- **Build plugin package**: `npm run build`

### Architecture

The plugin follows the principles of clean architecture and dependency injection:

- **Core**: Base plugin functionality and container
- **Domain**: Business logic and interfaces
- **Admin**: WordPress admin UI integration
- **Media**: Media library integration and processing
- **Conversion**: Image conversion implementations
- **Utility**: Helper classes and services

## 🔄 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## ⭐ Credits

- WebP technology by Google
- AVIF technology by the Alliance for Open Media
