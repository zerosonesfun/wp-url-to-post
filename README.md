# WordPress URL to Post
This WordPress plugin allows authorized users to create posts by providing data via URL parameters. It enforces user rate limiting to prevent abuse and uses the blog's default category for post assignments.

Why? No reason other than I find it interesting to try creating blog posts in non-traditional ways. I'm sure this could be very useful for various niche and experimental purposes.

**Example URL (how to post):** `https://yoursite.com/post/create/?title=Your Title&content=Your sweet content!&tags=Tag1,Tag2`

**Security Features:**

1. **User Authentication:** Users must be logged in to create posts, ensuring that only authorized users can utilize the feature.

2. **Rate Limiting:** Users can create a new post via URL parameters only once every 5 minutes (adjustable). This rate limiting prevents excessive use.

3. **Default Category:** Posts are assigned to the blog's default category, reducing the risk of uncategorized content.

4. **Data Sanitization:** User-provided data (title, content, tags) is thoroughly sanitized to prevent security vulnerabilities like SQL injection and cross-site scripting (XSS).

5. **Input Validation:** The plugin validates input data to ensure it meets expected criteria, enhancing data integrity and security.

6. **Error Handling:** If errors occur during post creation or due to unauthorized access, the plugin provides clear error messages in the style of a default WordPress error page.
