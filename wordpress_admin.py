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


    def refresh_calendar(self, id=1):
        refresh_page = '{0}/wp-admin/options-general.php?page=google-calendar-events.php&action=refresh&id={1}'.format(self.insecure_domain, id)
        
        fields = self.read_hidden_fields(refresh_page)
        fields['gce_options[submit_refresh]'] = 'Refresh Feed'
        refresh_data = urllib.urlencode(fields)

        options_page = '{0}/wp-admin/options.php'.format(self.insecure_domain)
        
        root = self.send_request_and_return_dom(options_page, refresh_data)

        selector = CSSSelector('#setting-error-gce_refreshed p strong')

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
        

        
