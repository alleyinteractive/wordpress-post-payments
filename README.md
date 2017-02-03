# Post Payments
WordPress plugin that tracks story cost and payment due totals

## Features
Post Payments adds a "Story cost" meta box to the post edit screen for selected post types. It provides a report page which allows filtering by date, and displays a tally of all authors attached to posts with story costs entered in the given date range, along with the total payment due to them for the stories they have authored.

## Requirements
Post Payments currently requires Fieldmanager and Co-Authors Plus. If it is activated without Fieldmanager, the settings page and meta box will not available, and if it is activated without Co-Authors Plus, the report page will not be available.

## How to use
Activate the plugin and go to Settings -> Post Payments. Select the post types to which you want to add the "Story cost" meta box. Set your local currency symbol (the default is '$'). Then, instruct your editors to enter story costs as necessary on the post edit page, and then to avail themselves of the "Payments Report" page in the Tools menu in the dashboard.

## Roadmap
* Support sites using WP users rather than Co-Authors Plus (which describes almost no sites at Alley, currently).
* If multiple authors are attached to a post with a cost entered, each author will be credited that value in their payment due total. We could offer an option to treat these costs are split equally amongst authors, or credited in full to each author. Currently, editors would need to enter the cost paid per author when entering costs.
* Export the report page to CSV, in some potentiall useful format.

## Release Notes
1.0 - Initial release

1.1 - Add tags for reporting functionality only (not public)
* Feature - Add report-tags taxonomy
* Feature - Add repeating field metabox to allow user to add multiple tags w/ ability to edit only from term screen
* Feature - Update csv report export to include column containing tags
* Bug - Fixed issue where commas in post titles would create additional columns in the report output.

1.2 - Add cost to guest authors and relate it to posts when author is selected.

1.3 - Bug fix for ERR_RESPONSE_HEADERS_MULTIPLE_CONTENT_DISPOSITION when trying to download the report in Chrome
