# Bulk CSV Importer with Datasheet Upload

A WordPress plugin that enables you to bulk upload entries into a custom post type (`products`) using a CSV file. The plugin also supports uploading images and datasheets from URLs and associating them with the posts.

## Features

- Import bulk data into the `products` custom post type from a CSV file.
- Automatically upload and set featured images from image URLs in the CSV file.
- Upload and associate datasheets (PDF or other files) from URLs in the CSV file.
- Add custom meta fields for each entry.
- Simple admin interface for uploading the CSV file.

## Requirements

- WordPress 5.0 or higher.
- PHP 7.4 or higher.

## Installation

1. Download the plugin as a `.zip` file.
2. Navigate to `Plugins > Add New` in the WordPress admin dashboard.
3. Click `Upload Plugin` and select the `.zip` file.
4. Install and activate the plugin.

## Usage

1. Go to `Tools > Bulk CSV Importer` in the WordPress admin dashboard.
2. Upload a CSV file in the format specified below.
3. The plugin will import the data, including uploading images and datasheets.

## CSV File Format

The CSV file should include the following columns (headers):

- **item-name**: The title of the product.
- **featured-image**: The URL of the featured image.
- **datesheets**: The URL of the datasheet (e.g., a PDF file).
- Additional columns will be treated as custom meta fields.

### Example CSV

```csv
item-name,featured-image,datesheets,custom-field-1,custom-field-2
Product 1,https://example.com/image1.jpg,https://example.com/datasheet1.pdf,Value 1,Value 2
Product 2,https://example.com/image2.jpg,https://example.com/datasheet2.pdf,Value 3,Value 4
```
