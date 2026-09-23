import { api } from '@/lib/api'
import { type MessageTemplate } from '@/types/message-templates'

export const messageTemplatesQueryKey = ['message-templates'] as const

export async function fetchMessageTemplates(): Promise<MessageTemplate[]> {
  const { data } = await api.get<{ templates: MessageTemplate[] }>(
    '/api/message-templates'
  )
  return data.templates
}

export async function createMessageTemplate(payload: {
  name: string
  body: string
}): Promise<MessageTemplate> {
  const { data } = await api.post<{ template: MessageTemplate }>(
    '/api/message-templates',
    payload
  )
  return data.template
}

export async function updateMessageTemplate(
  id: number,
  payload: { name: string; body: string }
): Promise<MessageTemplate> {
  const { data } = await api.patch<{ template: MessageTemplate }>(
    `/api/message-templates/${id}`,
    payload
  )
  return data.template
}

export async function deleteMessageTemplate(id: number): Promise<void> {
  await api.delete(`/api/message-templates/${id}`)
}
