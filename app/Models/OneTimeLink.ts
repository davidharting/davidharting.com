import crypto from 'node:crypto'
import { DateTime } from 'luxon'
import { BaseModel, beforeCreate, column } from '@ioc:Adonis/Lucid/Orm'

export default class OneTimeLink extends BaseModel {
  @column({ isPrimary: true })
  public id: string

  @column()
  public encryptedMessage: string

  @column()
  public signedUrl: string

  @column.dateTime({ autoCreate: true })
  public createdAt: DateTime

  @column.dateTime({ autoCreate: true, autoUpdate: true })
  public updatedAt: DateTime

  @beforeCreate()
  public static assignUuid(oneTimeLink: OneTimeLink) {
    if (!oneTimeLink.id) {
      oneTimeLink.id = crypto.randomUUID()
    }
  }
}
