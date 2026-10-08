import { developerApiVisible } from '@/lib/features'
import { LegalLayout, LegalSection } from './layout'

export function TermsPage() {
  const section = (number: number) =>
    developerApiVisible ? number : number - 1
  return (
    <LegalLayout title='Terms of Service'>
      <LegalSection title='1. Who these terms are with'>
        <p>
          These terms are between you and SOSCOM Technologies Company Limited
          (“Soscom”, “we”, “us”). They apply when you use Soscom SMS at
          sms.soscom.co.tz, including the dashboard{developerApiVisible
            ? ', the public SMS API,'
            : ','}{' '}
          and related billing and support.
        </p>
        <p>
          By creating an account or using the service, you agree to these
          terms. If you use Soscom SMS for a business, you confirm that you
          can bind that business.
        </p>
      </LegalSection>

      <LegalSection title='2. The service'>
        <p>
          {developerApiVisible ? (
            <>
              Soscom SMS is a Tanzanian bulk SMS platform. You can send SMS
              from the dashboard or through an API key, buy SMS credits, keep
              contacts, request Sender IDs, and view delivery reports.
            </>
          ) : (
            <>
              Soscom SMS is a Tanzanian bulk SMS platform. You can send SMS
              from the dashboard, buy SMS credits, keep contacts, request
              Sender IDs, and view delivery reports.
            </>
          )}
        </p>
        <p>
          We send traffic through our own wholesale provider. You deal only
          with Soscom. You do not receive provider credentials, provider
          endpoints, or a right to contact the wholesale network directly.
        </p>
      </LegalSection>

      <LegalSection title='3. Accounts'>
        <p>
          You must give a real name and email, keep your password private, and
          finish your business profile before sending SMS. We may ask you to
          confirm a sign-in with a one-time code sent to your email.
        </p>
        <p>
          {developerApiVisible ? (
            <>
              You are responsible for activity on your account, including API
              keys you create. If you think someone else has access, change
              your password and revoke the keys.
            </>
          ) : (
            <>
              You are responsible for activity on your account. If you think
              someone else has access, change your password.
            </>
          )}
        </p>
        <p>
          We may suspend an account that is unpaid, abused, or used in a way
          that breaks these terms or Tanzanian law.
        </p>
      </LegalSection>

      <LegalSection title='4. SMS credits and payment'>
        <p>
          SMS is prepaid. New accounts receive a small welcome balance so you
          can try the product. After that you buy credits through the wallet.
        </p>
        <p>
          Payment is by mobile money through Snippe. You approve the amount
          shown at checkout on your phone with M-Pesa, Airtel Money, Mixx by
          Yas, or Halotel. SMS credits are added only after that approval is
          confirmed. A prompt you do not approve adds nothing.
        </p>
        <p>
          Price depends on the volume tier shown at checkout. Each 160-character
          GSM part of a message uses 1 SMS per recipient. Unicode / Swahili
          messages use a shorter part length and can cost more SMS. The send
          form shows the estimate before you confirm.
        </p>
        <p>
          Credits are for sending SMS on Soscom SMS. They are not cash, cannot
          be transferred to another customer, and are not refunded except where
          Tanzanian law requires it or we charged you in error.
        </p>
      </LegalSection>

      <LegalSection title='5. Sender IDs'>
        <p>
          Shared Soscom Sender IDs (such as HARUSI, MWALIKO, MCHANGO, and SEND
          OFF) are available when they appear in your account. A custom Sender
          ID is a request, not a right.
        </p>
        <p>
          We review custom names, then submit approved names to the network by
          hand. Approval can take time and can be refused. Bank names, public
          institutions, and other protected names are blocked. You may only
          send from a Sender ID that belongs to your account.
        </p>
      </LegalSection>

      <LegalSection title='6. What you may send'>
        <p>You must only send to people who expect your messages.</p>
        <p>You must not use Soscom SMS to send:</p>
        <ul className='list-disc space-y-1 ps-5'>
          <li>spam, phishing, or messages that impersonate a bank or government body</li>
          <li>threats, harassment, or content that is illegal in Tanzania</li>
          <li>malware links or attempts to break another system</li>
          <li>messages from a Sender ID you do not own</li>
        </ul>
        <p>
          You must keep recipient lists accurate. Tanzanian numbers are stored
          in international format. You are responsible for the text you send
          and for any complaint that follows.
        </p>
      </LegalSection>

      <LegalSection title='7. Delivery'>
        <p>
          We queue and send messages in good faith and show the status we
          receive from the network. Delivery depends on the recipient handset,
          the mobile network, DND rules, and coverage. We do not promise that
          every SMS will arrive, or that it will arrive at a set time.
        </p>
        <p>
          Failed or pending messages still use credits once we have accepted
          the send, unless we decide a failure was caused by our own system
          error.
        </p>
      </LegalSection>

      {developerApiVisible ? (
        <LegalSection title='8. API use'>
          <p>
            API keys let your own software send SMS through sms.soscom.co.tz/api/v1.
            Keep keys secret. You must stay within the published rate limits and
            only send traffic you are allowed to send under these terms.
          </p>
          <p>
            We may throttle, revoke a key, or reject a request that looks abusive
            or unsafe.
          </p>
        </LegalSection>
      ) : null}

      <LegalSection title={`${section(9)}. Availability`}>
        <p>
          We aim to keep Soscom SMS available, but maintenance, network faults,
          and provider outages happen. The service is provided as is. We are
          not liable for lost business, lost data, or messages that do not
          arrive, except for death or personal injury caused by our negligence
          or other liability that Tanzanian law does not let us exclude.
        </p>
        <p>
          If we are liable, the amount is limited to the SMS credits you paid
          for in the three months before the claim.
        </p>
      </LegalSection>

      <LegalSection title={`${section(10)}. Ending the account`}>
        <p>
          You can stop using Soscom SMS at any time. We can close or suspend
          an account if you break these terms, if payment is not confirmed, or
          if we have to stop the service.
        </p>
        <p>
          Unused credits lapse when the account is closed, unless the law says
          otherwise.
        </p>
      </LegalSection>

      <LegalSection title={`${section(11)}. Changes`}>
        <p>
          We may update these terms. The date at the top of this page is the
          current version. Continued use after a change means you accept the
          new terms.
        </p>
      </LegalSection>

      <LegalSection title={`${section(12)}. Law and contact`}>
        <p>
          These terms are governed by the laws of the United Republic of
          Tanzania. Disputes are handled in Tanzanian courts.
        </p>
        <p>
          Questions: sms@soscom.co.tz. Company: SOSCOM Technologies Company
          Limited.
        </p>
      </LegalSection>
    </LegalLayout>
  )
}
