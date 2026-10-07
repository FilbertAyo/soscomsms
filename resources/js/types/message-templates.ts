export type MessageTemplateType = 'transactional' | 'promotional' | 'informational'

export const messageTemplateTypeLabels: Record<MessageTemplateType, string> = {
  transactional: 'Transactional',
  promotional: 'Promotional',
  informational: 'Informational',
}

export type MessageTemplate = {
  id: number
  name: string
  type: MessageTemplateType
  body: string
  encoding: 'gsm' | 'unicode'
  characterCount: number
  segmentCount: number
}
