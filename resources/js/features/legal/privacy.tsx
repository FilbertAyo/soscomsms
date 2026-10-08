import { developerApiVisible, webhooksVisible } from '@/lib/features'
import { LegalLayout, LegalSection } from './layout'

export function PrivacyPage() {
  return (
    <LegalLayout title='Privacy Policy'>
      <LegalSection title='1. Who we are'>
        <p>
          SOSCOM Technologies Company Limited runs Soscom SMS at
          sms.soscom.co.tz. This page explains what we collect when you use
          {developerApiVisible
            ? ' the dashboard or the SMS API, and how we use it.'
            : ' the dashboard, and how we use it.'}
        </p>
        <p>
          Contact for privacy questions: sms@soscom.co.tz.
        </p>
      </LegalSection>

      <LegalSection title='2. What we collect'>
        <p>Account data: name, email, password hash, business name, job title, and Tanzanian mobile number.</p>
        <p>
          Sign-in data: email, password check, remember-me cookie if you ask
          for it, and a one-time code we send to your email.
        </p>
        <p>
          Messaging data: Sender IDs, campaign text, recipient names and
          numbers you upload, group names, send times, and delivery status.
        </p>
        <p>
          Billing data: SMS balance, credit history, the mobile number used
          to pay, the payment reference, and the amount in TZS. We do not
          store your mobile-money PIN.
        </p>
        {developerApiVisible ? (
          <p>
            Developer data: API key names and prefixes, request paths
            {webhooksVisible
              ? ', status codes, and webhook delivery attempts.'
              : ', and status codes.'}{' '}
            We do not show full live keys again after you create them.
          </p>
        ) : null}
        <p>
          Technical data: IP address, browser, and basic server logs needed to
          run and secure the service.
        </p>
      </LegalSection>

      <LegalSection title='3. Why we use it'>
        <p>We use this data to:</p>
        <ul className='list-disc space-y-1 ps-5'>
          <li>create and secure your account</li>
          <li>send the SMS you submit and show delivery reports</li>
          <li>price and confirm SMS purchases</li>
          <li>review Sender ID requests</li>
          <li>email sign-in codes, password resets, payment notices, and Sender ID decisions</li>
          <li>keep an audit trail of admin actions</li>
          <li>find abuse, spam, or unpaid accounts</li>
        </ul>
        <p>
          We do not sell customer lists. We do not use your recipient numbers
          to send Soscom marketing.
        </p>
      </LegalSection>

      <LegalSection title='4. Who else sees it'>
        <p>
          Wholesale SMS provider: we pass the Sender ID, destination number,
          and message text needed to deliver SMS. We do not give them your
          {developerApiVisible
            ? ' password, API keys, or billing records.'
            : ' password or billing records.'}
        </p>
        <p>
          Payment: Snippe (snippe.sh) receives your name, email, paying
          phone number, and the amount so it can send a PIN prompt to
          M-Pesa, Airtel Money, Mixx by Yas, or Halotel. We store the
          result of that payment. We never receive or store the PIN.
        </p>
        <p>
          Hosting and email: servers and mail used to run sms.soscom.co.tz and
          to send account mail.
        </p>
        <p>
          Law: we may disclose data if Tanzanian law or a lawful order
          requires it.
        </p>
        <p>
          Your staff: anyone you give login access
          {developerApiVisible ? ' or an API key' : ''} can see the data in
          that account.
        </p>
      </LegalSection>

      <LegalSection title='5. Recipients you upload'>
        <p>
          Contact names and numbers belong to your account. We store them so
          you can send SMS and see reports. You must have a reason to hold
          those numbers and to message them.
        </p>
        <p>
          Deactivating a contact keeps the row but stops it being selected for
          new campaigns. Deleting imported files from your computer does not
          remove contacts already saved in Soscom SMS.
        </p>
      </LegalSection>

      <LegalSection title='6. Cookies and sessions'>
        <p>
          We use a session cookie so you stay signed in while you use the
          dashboard. If you tick “Remember me for 30 days”, we set a longer
          cookie so you do not have to sign in every visit.
        </p>
        <p>
          We do not use advertising cookies and we do not run third-party ad
          trackers on Soscom SMS.
        </p>
      </LegalSection>

      <LegalSection title='7. How long we keep it'>
        <p>
          Account and billing records are kept while the account is open and
          for a period after that so we can handle disputes, tax, and abuse
          reports.
        </p>
        <p>
          Delivery reports and campaign text are kept so you can search
          history and so we can investigate faults. Sign-in codes expire after
          a few minutes and are not kept as reusable passwords.
        </p>
      </LegalSection>

      <LegalSection title='8. Security'>
        <p>
          Passwords are hashed. Provider credentials and API secrets are
          stored so customers cannot read them. Sessions use CSRF protection.
          No method is perfect; you still need a strong password
          {developerApiVisible
            ? ' and to keep API keys off public computers.'
            : '.'}
        </p>
      </LegalSection>

      <LegalSection title='9. Your choices'>
        <p>
          You can update your name, email, business profile, and password in
          Settings. You can add, edit, deactivate, or import contacts.
          {developerApiVisible ? ' You can revoke API keys.' : ''}
        </p>
        <p>
          For a copy of your account data, or to ask us to close the account,
          email sms@soscom.co.tz from the address on the account.
        </p>
      </LegalSection>

      <LegalSection title='10. Children'>
        <p>
          Soscom SMS is for businesses and organisations. It is not aimed at
          children.
        </p>
      </LegalSection>

      <LegalSection title='11. Changes'>
        <p>
          If we change this policy we will update the date on this page.
        </p>
      </LegalSection>
    </LegalLayout>
  )
}