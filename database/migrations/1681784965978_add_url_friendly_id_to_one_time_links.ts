import BaseSchema from '@ioc:Adonis/Lucid/Schema'

export default class extends BaseSchema {
  protected tableName = 'one_time_links'

  public async up() {
    this.schema.alterTable(this.tableName, (table) => {
      table.string('public_id', 50).index()
    })
  }

  public async down() {
    this.schema.alterTable(this.tableName, (table) => {
      table.dropColumn('public_id')
    })
  }
}
