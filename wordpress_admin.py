from lxml.cssselect import CSSSelector
import urllib

from web_automation.website import Website


class WordpressAdmin(Website):
    def log_in(self):
        log_in_page = self.insecure_domain + '/wp-login.php'

        fields = self.read_hidden_fields(log_in_page)
        fields['log'] = self.login
        fields['pwd'] = self.password

        login_data = urllib.urlencode(fields)

        if self.debug:
            print 'Logging in...'

        response = self.send_request_with_retry(log_in_page, login_data)
        response.close()

    def refresh_calendars(self):
        refresh_page = '{0}/wp-admin/edit.php?post_type=gce_feed'.format(
            self.insecure_domain)

        root = self.send_request_and_return_dom(refresh_page)
        selector = CSSSelector('span.clear_cache a')

        for element in selector(root):
            self.refresh_calendar(element.get('href'))

    def refresh_calendar(self, href):
        root = self.send_request_and_return_dom('{0}{1}'.format(
            self.insecure_domain, href))

        selector = CSSSelector('#setting-error-gce-cache-updated p strong')

        notice_count = 0

        for notice in selector(root):
            print notice.text
            notice_count += 1

        if notice_count == 0:
            print 'No notices found - feed may not have been refreshed'

    def read_hidden_fields(self, url):
        root = self.send_request_and_return_dom(url)
        selector = CSSSelector('[type=hidden]')

        fields = {}

        for element in selector(root):
            fields[element.get('name')] = element.get('value')

        return fields
