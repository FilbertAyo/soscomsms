import { createFileRoute } from '@tanstack/react-router'
import { CampaignDetail } from '@/features/campaigns/detail'

export const Route = createFileRoute('/_authenticated/campaigns/$campaignId')({
  component: CampaignDetailRoute,
})

function CampaignDetailRoute() {
  const { campaignId } = Route.useParams()
  return <CampaignDetail campaignId={campaignId} />
}
