import { api } from '@/lib/api'
import {
  type MessageTemplate,
  type MessageTemplateType,
} from '@/types/message-templates'

export const messageTemplatesQueryKey = ['message-templates'] as const

export type MessageTemplatePayload = {
  name: string
  type: MessageTemplateType
  body: string
}

export async function fetchMessageTemplates(): Promise<MessageTemplate[]> {
  const { data } = await api.get<{ templates: MessageTemplate[] }>(
    '/api/message-templates'
  )
  return data.templates
}

export async function createMessageTemplate(
  payload: MessageTemplatePayload
): Promise<MessageTemplate> {
  const { data } = await api.post<{ template: MessageTemplate }>(
    '/api/message-templates',
    payload
  )
  return data.template
}

export async function updateMessageTemplate(
  id: number,
  payload: MessageTemplatePayload
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
