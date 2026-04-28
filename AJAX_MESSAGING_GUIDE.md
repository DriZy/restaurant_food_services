# Catering Chat AJAX Implementation

## Overview
This implementation adds AJAX functionality to the catering chat messaging system, allowing messages to be sent without page reloads on both the frontend and admin dashboard.

## Features

### ✅ Frontend Chat (Customer's Account Page)
- Messages are sent via AJAX without page reload
- Users can continue viewing the chat while typing
- Real-time message display after sending
- Error/Success notifications with auto-dismiss
- Smooth animations for notifications

### ✅ Admin Dashboard (Edit Catering Request)
- Same AJAX functionality as frontend
- Admin can reply to customers without leaving the edit screen
- Messages appear instantly in the chat thread
- Works in the catering chat metabox

## How It Works

### Backend Implementation

#### 1. PHP AJAX Handler (`class-catering-module.php`)
- **New method**: `handle_catering_send_message_ajax()`
- **Action hooks**: 
  - `wp_ajax_restaurant_catering_send_message` (for logged-in users)
  - `wp_ajax_nopriv_restaurant_catering_send_message` (for non-logged-in users)

#### 2. Registration Hooks
- Added to `register_hooks()` in the `Catering_Module` class
- Registered AJAX actions for both authenticated and non-authenticated requests

#### 3. Security Features
- **Nonce verification** to prevent CSRF attacks
- **Capability checking** ensures only authorized users can send messages
- **Input sanitization** using `sanitize_textarea_field()`
- **Permission validation** checks post ownership and admin status

#### 4. Frontend Asset Enqueuing
- **New method**: `enqueue_catering_frontend_assets()`
- Enqueues JavaScript file with AJAX configuration
- Passes AJAX URL and nonce via `wp_localize_script()`

### Frontend Implementation

#### JavaScript File: `catering-chat-ajax.js`

**Main Functionality:**
1. **Form Interception**: Captures comment form submissions
2. **AJAX Request**: Sends message data to server via `fetch()` API
3. **Dynamic Message Addition**: Appends new message to chat without reload
4. **User Feedback**: Shows success/error notifications
5. **Auto-Scroll**: Scrolls chat to latest message

**Key Features:**
- Uses modern Fetch API (better than jQuery AJAX)
- Graceful degradation if globals not found
- Auto-removes notifications after timeout
- Disables submit button during request
- Supports both frontend and admin metabox contexts

## API Endpoints

### AJAX Endpoint
**URL**: `wp-admin/admin-ajax.php`
**Action**: `restaurant_catering_send_message`
**Method**: POST

### Request Parameters
```json
{
  "action": "restaurant_catering_send_message",
  "nonce": "security_nonce_token",
  "post_id": 123,
  "message": "User's message text"
}
```

### Response (Success)
```json
{
  "success": true,
  "data": {
    "message": "Message sent successfully.",
    "commentHtml": "<article class=\"restaurant-catering-chat-message\">...</article>",
    "commentId": 456
  }
}
```

### Response (Error)
```json
{
  "success": false,
  "data": {
    "message": "Error message describing what went wrong"
  }
}
```

## Files Modified/Created

### Modified Files:
1. `/includes/modules/class-catering-module.php`
   - Added `enqueue_catering_frontend_assets()` method
   - Added `handle_catering_send_message_ajax()` method
   - Registered AJAX hooks in `register_hooks()`

### Created Files:
1. `/assets/js/catering-chat-ajax.js`
   - Complete AJAX implementation for chat messaging

## Installation & Usage

### 1. Automatic Enqueuing
- JavaScript automatically enqueues on all frontend pages (via `wp_enqueue_scripts`)
- No manual activation required

### 2. CSS Notification Styles
- Added automatically via JavaScript
- Includes animations and responsive styling
- Error notifications: Red background
- Success notifications: Green background

### 3. Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Requires Fetch API support
- Graceful fallback if no comments form found

## Error Handling

The system handles various error scenarios:

1. **Missing Nonce**: Security check failed
2. **Invalid Post ID**: Catering request not found
3. **Empty Message**: User tried to send blank message
4. **Permission Denied**: User not authorized to comment
5. **Not Logged In**: User must be authenticated
6. **Database Failures**: Comment creation failed
7. **Network Errors**: Fetch request failed

All errors display user-friendly messages.

## Performance

- No full page reload = faster response
- AJAX request completes in ~100-500ms
- Comment HTML pre-rendered on server
- No database re-queries after insert
- Minimal DOM manipulation

## Security Considerations

1. **Nonce Verification**: Every request verified with WordPress nonce
2. **Capability Checking**: Only post author or admin can reply
3. **Input Sanitization**: All data sanitized before storage
4. **Escaping**: HTML output properly escaped
5. **CSRF Protection**: Standard WordPress nonce protection

## Testing

### To Test Frontend:
1. Create a catering request as a customer
2. Navigate to "My Catering Requests" on customer account page
3. Click "View Details & Chat"
4. Type a message in the textare and click "Send Message"
5. ✅ Message appears without page reload

###To Test Admin:
1. Go to WordPress Admin
2. Navigate to Catering → Catering Requests
3. Click "Edit Request" on any request
4. Scroll to "Discussion / Chat" metabox
5. Type a message and click "Send Message"
6. ✅ Message appears without page refresh

## Troubleshooting

### Messages not sending?
- Check browser console for JavaScript errors
- Verify nonce is being generated: `wp_create_nonce('restaurant_catering_chat_nonce')`
- Ensure user is logged in
- Check user has permission to reply

### AJAX returns 403 (Permission Denied)?
- Verify nonce is valid
- Check user is post author or admin
- Ensure `is_user_logged_in()` returns true

### Messages appear but don't show?
- Check notification CSS is applied
- Verify `.restaurant-catering-chat-messages` container exists
- Check browser console for DOM manipulation errors

## Future Improvements

Potential enhancements:
- Real-time notifications using WebSockets
- Message editing/deletion
- Typing indicators
- Message reactions/emojis
- File attachments
- Message search
- Notification emails
- Push notifications

## Support

For issues or questions, refer to:
- PHP code comments in `class-catering-module.php`
- JavaScript code comments in `catering-chat-ajax.js`
- WordPress AJAX documentation: https://developer.wordpress.org/plugins/admin/ajax/

