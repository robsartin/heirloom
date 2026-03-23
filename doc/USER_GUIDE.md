# Heirloom Gallery - User Guide

Heirloom Gallery is a site where paintings are available to claim. Browse the gallery, find paintings you love, and let the site owner know you want one.

## Creating an Account

Registration uses a magic link sent to your email. This verifies you own the email address and creates your account in one step.

1. Go to the **Register** page
2. Enter your **name** and **email address**
3. Click **Register**
4. Check your email inbox for a message from Heirloom Gallery
5. Click the **login link** in the email
6. You're logged in and prompted to set a password (optional but recommended)

### About the email login link

- Arrives within a few minutes (check your spam folder if you don't see it)
- Expires after **1 hour**
- Can only be used **once** — clicking it a second time will show "Invalid or expired login link"
- Is unique to your email address — it cannot be used by anyone else
- Contains a cryptographically random token (64 hex characters) that is marked as used after your first click

### Setting a password

After your first login via magic link, you'll be prompted to set a password. This is optional but lets you log in faster next time without waiting for an email. Passwords must be at least 8 characters.

## Logging In

### With a password

1. Go to the **Log In** page
2. Enter your email and password
3. Click **Log In**

### With Google (existing accounts only)

1. Go to the **Log In** page
2. Click **Sign in with Google**
3. Choose your Google account

Google login only works if you've already registered. If there's no account matching your Google email, you'll be redirected to the registration page.

### With a magic link (no password needed)

1. Go to the **Log In** page
2. Enter your email address and **leave the password field blank**
3. Click **Log In**
4. Check your email and click the login link

This is useful if you forgot your password or never set one.

## Your Profile

Click **Profile** in the navigation bar to manage your account.

### Shipping address

Enter your shipping address so the site owner knows where to send paintings you're awarded. This is visible to the admin when they're deciding who to award a painting to — having an address on file may help.

You can update your address at any time.

### Changing your password

From the profile page, click **Change password** to update your login password.

## Browsing Paintings

The home page shows all available paintings in a grid. Each painting shows:
- A thumbnail image (aspect ratio preserved)
- The painting's title
- How many people have expressed interest

Paintings are shown 12 per page. Use the pagination controls at the bottom to browse through pages.

Click any painting to see it full-size with more details.

## Expressing Interest

When you find a painting you'd like to have:

1. Click the painting to view its detail page
2. Optionally write a message explaining why you want it (the site owner reads these when deciding)
3. Click **I want this painting**

You can express interest in as many paintings as you like.

### Changing your mind

To withdraw your interest, go to the painting's detail page and click **Withdraw interest**. This toggles off your claim.

### What happens next

The site owner reviews who wants each painting and picks one person to receive it. Once a painting is awarded:
- It disappears from the public gallery
- The site owner will arrange shipping using your address on file
- You may receive a tracking number once the painting is shipped

---

# Admin Guide

This section is for site administrators who manage the paintings and award them to users.

## Accessing the Admin Dashboard

Log in with your admin account. You'll see **Admin** and **Upload** links in the navigation bar.

Click **Admin** to reach the dashboard.

## Dashboard Overview

The dashboard shows a table of all paintings with:

| Column | Description |
|--------|-------------|
| Thumbnail | Small preview image |
| Title | Painting name (click column header to sort) |
| Interested | Number of people who want it (sortable) |
| Last Interest | When someone most recently expressed interest (sortable) |
| Status | Available or Awarded (and to whom) |
| Uploaded | When the painting was added (sortable) |
| Actions | Link to manage the painting |

### Sorting

Click any sortable column header to sort by that column. Click again to reverse the sort direction. An arrow indicates the current sort.

### Filters

Use the filter bar at the top:

- **Available** — paintings not yet awarded (default)
- **Wanted** — available paintings that at least one person wants
- **Awarded** — paintings that have been given away
- **All** — everything

The **Wanted** filter is particularly useful for deciding what to award next.

## Uploading Paintings

1. Click **Upload** in the navigation (or the **Upload Paintings** button on the dashboard)
2. Fill in the form:
   - **Title** — required for single uploads, optional for batch (filenames are used instead)
   - **Description** — optional, shown on the painting's detail page
   - **Images** — select one or more PNG or JPEG files
3. Click **Upload**

### Batch uploads

Select multiple files at once. Each file becomes a separate painting. The title logic:
- If you leave the title blank, each painting is titled by its filename (without extension)
- If you provide a title, it becomes a prefix: "Your Title - filename"

### Upload limits

The server accepts uploads up to 256MB per request and up to 100 files at a time. If you see an "Upload too large" error, upload fewer files per batch.

## Managing a Painting

Click **Manage** on any painting in the dashboard to:

### Edit title and description

The title and description fields are editable. Change them and click **Save Changes**.

### View interested users

A list shows everyone who expressed interest, including:
- Their name and email
- Whether they have a shipping address on file
- Their message (if they wrote one)

### Award a painting

Click the **Award** button next to the user you want to give it to. You'll be asked to confirm. Once awarded:
- The painting disappears from the public gallery
- It moves to the "Awarded" filter on the dashboard
- The recipient's name, email, and shipping address are displayed
- The award is logged with a timestamp and who made the decision

### Shipping and tracking

After awarding a painting:
1. Check the recipient's **shipping address** (shown on the manage page)
2. If no address is on file, contact them via email to get one
3. After shipping, enter the **tracking number** and click **Save Tracking**

### Award history

Every award and unassign action is logged. The **Award History** table at the bottom of the manage page shows:
- What action was taken (awarded or unassigned)
- Which user was affected
- Which admin performed the action
- When it happened

### Unassign a painting

If you change your mind after awarding, click **Unassign**. This:
- Returns the painting to the available pool
- Clears the tracking number
- Logs the unassign action in the award history

### Delete a painting

Click **Delete Painting** to permanently remove it. This deletes the image file, all interest records, and the award history. This cannot be undone.
