# WPManage by Timepro - WordPress Plugin

## Overview

**WPManage by Timepro** is a WordPress management plugin that enables centralized monitoring and management of WordPress installations through the Timepro WP Dashboard. The plugin automatically collects site information and sends it to a central server for monitoring.

## Plugin Structure

```
wpmanage-by-timepro/
├── wpmanage-by-timepro.php  # Main plugin file
├── updater.php               # Auto-update handler
└── wp_profiler.php          # Data collection and transmission
```

## Features

### 1. **Automatic Data Collection**
The plugin collects the following information:
- **Site URL**: WordPress site URL
- **WordPress Version**: Current WP version
- **PHP Version**: Server PHP version
- **MySQL Version**: Database version
- **Active Theme**: Currently active theme name
- **Admin Username**: Primary admin user login
- **Admin Email**: Primary admin email
- **Site Email**: General site email from settings
- **Plugin Version**: Current plugin version
- **Last Backup**: UpdraftPlus last backup timestamp (if available)

### 2. **Auto-Update System**
- Checks for updates from custom update server
- Downloads and installs updates automatically
- Update URL: `https://beta.timepro.in/wp/wp-plugin-info.json`
- Plugin package URL: `https://beta.timepro.in/wp/wpmanage-by-timepro.zip`

### 3. **Scheduled Data Transmission**
- Sends collected data every **12 hours**
- Uses WordPress transients to prevent duplicate sends
- Endpoint: `https://beta.timepro.in/api/wp-data?passwd=ThisIsGood`
- Method: POST with JSON payload

### 4. **Manual Data Access**
- **Pull Data**: `?_pull_profile_data=yes` - Returns JSON data and exits
- **View Data**: `?_profile_data=1` - Displays formatted data (for debugging)

## Installation

### Method 1: Upload via WordPress Admin
1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin

### Method 2: Manual Upload
1. Extract the ZIP file
2. Upload `wpmanage-by-timepro` folder to `/wp-content/plugins/`
3. Activate through WordPress Admin → Plugins

## Configuration

### Constants Defined
```php
TIMEPRO_URL         = 'https://beta.timepro.in'
WPMANAGE_VERSION    = '0.0.2'
WPMANAGE_UPDATE_URL = TIMEPRO_URL . '/wp/wp-plugin-info.json'
LAST_SENT_AT        = 'managewp_last_sent_at'
```

### No Settings Required
The plugin works automatically after activation. No configuration needed.

## API Endpoints

### 1. Update Check Endpoint
**URL**: `https://beta.timepro.in/wp/wp-plugin-info.json`

**Response Format**:
```json
{
    "name": "WPManage by Timepro",
    "slug": "wpmanage-by-timepro",
    "version": "0.0.2",
    "new_version": "0.0.2",
    "download_url": "https://beta.timepro.in/wp/wpmanage-by-timepro.zip",
    "homepage": "https://beta.timepro.in",
    "description": "...",
    "changelog": "..."
}
```

### 2. Data Transmission Endpoint
**URL**: `https://beta.timepro.in/api/wp-data?passwd=ThisIsGood`

**Method**: POST

**Headers**:
```
Content-Type: application/json
```

**Payload Example**:
```json
{
    "url": "https://example.com",
    "active_theme": "Twenty Twenty-Four",
    "wp_version": "6.4.3",
    "php_version": "8.1.0",
    "mysql_version": "8.0.32",
    "admin_username": "admin",
    "admin_email": "admin@example.com",
    "site_email": "info@example.com",
    "plugin_version": "0.0.2",
    "last_backup": "2026-02-13 10:30:00"
}
```

## How It Works

### Data Collection Flow
```
WordPress Init Hook
    ↓
WPProfiler::collect_data()
    ↓
Collects site information
    ↓
Stores in static $data array
    ↓
Check transient LAST_SENT_AT
    ↓
If not set (>12 hours passed)
    ↓
WPProfiler::send_data()
    ↓
POST to Timepro API
    ↓
Set transient for 12 hours
```

### Update Check Flow
```
WordPress checks for plugin updates
    ↓
WPManage_Updater::check_for_update()
    ↓
Fetch wp-plugin-info.json
    ↓
Compare versions
    ↓
If new version available
    ↓
Add to update transient
    ↓
WordPress shows update notification
    ↓
User clicks update
    ↓
Download from download_url
    ↓
Install and activate
```

## Releasing New Versions

### Step 1: Update Plugin Files
1. Update version in `wpmanage-by-timepro.php`:
   ```php
   define('WPMANAGE_VERSION', '0.0.3'); // Increment version
   ```

2. Update plugin header:
   ```php
   /**
    * Version: 0.0.3
    */
   ```

### Step 2: Update wp-plugin-info.json
```json
{
    "version": "0.0.3",
    "new_version": "0.0.3",
    "last_updated": "2026-02-15 10:00:00",
    "sections": {
        "changelog": "<h4>0.0.3</h4>\n<ul>\n<li>New feature added</li>\n<li>Bug fixes</li>\n</ul>\n\n<h4>0.0.2</h4>\n<ul>\n<li>Initial release</li>\n</ul>"
    }
}
```

### Step 3: Create ZIP Package
```bash
cd /Users/dakshhmehta/Herd/taskAssist/wp
zip -r wpmanage-by-timepro.zip wpmanage-by-timepro/
```

### Step 4: Upload Files
1. Upload `wpmanage-by-timepro.zip` to `public/wp/wpmanage-by-timepro.zip`
2. Update `public/wp/wp-plugin-info.json` with new version info

### Step 5: WordPress Auto-Updates
- WordPress sites will check for updates automatically
- Users will see update notification in admin
- One-click update available

## Security Considerations

### Current Implementation
⚠️ **Password in URL**: `?passwd=ThisIsGood` is not secure
⚠️ **No Authentication**: API endpoint has weak authentication
⚠️ **Sensitive Data**: Sends admin username and email

### Recommended Improvements
1. **Use API Keys**: Generate unique API key per installation
2. **Hash Passwords**: Don't send passwords in URL
3. **HTTPS Only**: Enforce SSL/TLS
4. **Rate Limiting**: Prevent abuse
5. **Data Encryption**: Encrypt sensitive data in transit
6. **Nonce Verification**: Add WordPress nonces for security

## Debugging

### Enable Debug Mode
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### View Collected Data
Visit: `https://yoursite.com/?_profile_data=1`

### Pull Data Manually
Visit: `https://yoursite.com/?_pull_profile_data=yes`

### Force Send Data
Visit: `https://yoursite.com/?_profile_data=1` (clears transient and shows data)

### Check Transient
```php
// In WordPress admin or via plugin
$last_sent = get_transient('managewp_last_sent_at');
var_dump($last_sent);
```

## Troubleshooting

### Plugin Not Sending Data
1. Check if transient is set: `get_transient('managewp_last_sent_at')`
2. Delete transient to force send: `delete_transient('managewp_last_sent_at')`
3. Check cURL is enabled on server
4. Verify API endpoint is accessible

### Updates Not Showing
1. Verify `wp-plugin-info.json` is accessible
2. Check version numbers are correct
3. Clear WordPress transients
4. Check for JSON syntax errors

### UpdraftPlus Backup Not Detected
1. Verify UpdraftPlus is installed and activated
2. Check if backups exist
3. Verify option name: `updraft_backup_history`

## File Locations

### Plugin Files (Development)
```
/Users/dakshhmehta/Herd/taskAssist/wp/wpmanage-by-timepro/
```

### Public Files (Production)
```
/Users/dakshhmehta/Herd/taskAssist/public/wp/
├── wp-plugin-info.json          # Update manifest
└── wpmanage-by-timepro.zip      # Plugin package
```

## Version History

### 0.0.2 (Current)
- Initial release
- Automatic data collection
- Auto-update functionality
- UpdraftPlus integration
- 12-hour sync interval

## Future Enhancements

1. **Dashboard Integration**: Build admin panel in WordPress
2. **Selective Data**: Allow users to choose what data to send
3. **Multiple Sites**: Support for multisite installations
4. **Performance Metrics**: Add site speed and performance data
5. **Plugin/Theme List**: Collect installed plugins and themes
6. **Security Scanning**: Basic security checks
7. **Uptime Monitoring**: Track site availability
8. **Error Logging**: Collect PHP errors and warnings

## Support

For issues or questions:
- **Website**: https://beta.timepro.in
- **Email**: support@timepro.in (if available)

## License

GPL v2 or later
