'''
Link Count - Gets the number of links to a page in a Wikimedia project
Copyright (C) 2021 Brandon Fowler

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
'''

import json
import requests
from collections import OrderedDict 
from flask import Flask, render_template, request
import MySQLdb

app = Flask(__name__)

app.jinja_env.filters['sepnum'] = lambda x: '{:,}'.format(x)

class Fetcher:
	def __init__(self, cur, namespace, title):
		self.cur = cur
		self.namespace = namespace
		self.title = title

	def fetch(self, table, ns_key, title_key, indirects = True, subtract = 0):
		self.cur.execute(
			'SELECT COUNT(*) FROM %s WHERE %s=%%s AND %s=%%s'
			% (table, ns_key, title_key),
			(self.namespace, self.title)
		)

		count = self.cur.fetchone()[0] - subtract

		if indirects:
			self.cur.execute(
				'SELECT COUNT(*) FROM redirect '
				'JOIN page ON rd_from=page_id '
				'JOIN %s ON %s=page_namespace AND %s=page_title '
				'WHERE rd_namespace=%%s AND rd_title=%%s'
				% (table, ns_key, title_key),
				(self.namespace, self.title)
			)

			indirectcount = self.cur.fetchone()[0]

			return {
				'direct': count,
				'indirect': indirectcount,
				'all': count + indirectcount
			}

		return count

	def fetch_wo_ns(self, table, to_key, indirects = True):
		self.cur.execute(
			'SELECT COUNT(*) FROM %s WHERE %s=%%s'
			% (table, to_key),
			(self.title,)
		)
	
		count = self.cur.fetchone()[0]

		if indirects:
			self.cur.execute(
				'SELECT COUNT(*) FROM redirect '
				'JOIN page ON rd_from=page_id '
				'JOIN %s ON %s=page_title '
				'WHERE rd_namespace=%%s AND rd_title=%%s'
				% (table, to_key), (self.namespace, self.title)
			)

			indirectcount = self.cur.fetchone()[0]

			return {
				'direct': count,
				'indirect': indirectcount,
				'all': count + indirectcount
			}

		return count

def main():
	page = request.args.get('page')
	project = request.args.get('project')
	title = None
	namespace = 0
	errors = OrderedDict()

	if not project and not page:
		return {
			'title': 'Link Count',
			'results': False
		}

	if not project:
		project = 'en.wikipedia.org'
	
	if not page:
		errors['page'] = 'Page name is required.'
	else:
		title = page.replace(' ', '_')
		title = title[0].upper() + title[1:]
 
	if project.endswith('_p'):
		project = project[:-2]
	elif project.startswith('https://'):
		project = project[7:]
	elif project.startswith('http://'):
		project = project[6:]

	db = MySQLdb.connect(
		host='metawiki.web.db.svc.eqiad.wmflabs',
		db='meta_p',
		read_default_file='../../../replica.my.cnf'
	)
	cur = db.cursor()

	cur.execute(
		'SELECT dbname, url FROM wiki '
		'WHERE dbname=%s OR url=%s '
		'LIMIT 1',
		(project, 'https://' + project)
	)

	projectentry = cur.fetchone()

	if not projectentry:
		errors['project'] = 'The project ' + project + ' does not exist.'

	if errors:
		return {
			'title': 'Error - Link Count',
			'page': page,
			'project': project,
			'errors' : errors,
			'results': False
		}

	dbname, url = projectentry
	project = url[8:]

	res = requests.get(url + '/w/api.php', params = {
		'action': 'query',
		'prop': 'info',
		'titles': title,
		'format': 'json'
	}).json()

	for info in res['query']['pages']:
		namespace = res['query']['pages'][info]['ns']

	if namespace != 0:
		title = title.split(':', 1)[1]

	cur.execute('USE %s_p' % dbname)

	fetcher = Fetcher(cur, namespace, title)
	# Don't count double redirects
	redirects = fetcher.fetch('redirect', 'rd_namespace', 'rd_title', indirects = False)
	wikilinks = fetcher.fetch('pagelinks', 'pl_namespace', 'pl_title', subtract = redirects)
	transclusions = fetcher.fetch('templatelinks', 'tl_namespace', 'tl_title')
	# Images links from redirects are also added to the imagelinks table under the redirect target
	filelinks = fetcher.fetch_wo_ns('imagelinks', 'il_to', indirects = False) if namespace == 6 else None
	categorylinks = fetcher.fetch_wo_ns('categorylinks', 'cl_to') if namespace == 14 else None

	cur.close()
	db.close()

	return {
		'title': page + ' on ' + project + ' - Link Count',
		'page': page,
		'project': project,
		'results': True,
		'filelinks': filelinks,
		'categorylinks': categorylinks,
		'wikilinks': wikilinks,
		'redirects': redirects,
		'transclusions': transclusions,
	}

@app.route('/', methods=['GET'])
def index():
	data = main()

	return render_template(
		'main.html',
		title = data['title'],
		errors = data.get('errors'),
		fields = [
			{
				'name': 'page',
				'label': 'Page Name',
				'value': data.get('page', '')
			},
			{
				'name': 'project',
				'label': 'Project',
				'value': data.get('project', '')
			}
		],
		values = [
			{
				'name': 'File links',
				'value': data['filelinks']
			},
			{
				'name': 'Category links',
				'value': data['categorylinks']
			},
			{
				'name': 'Wikilinks',
				'value': data['wikilinks']
			},
			{
				'name': 'Redirects',
				'value': data['redirects']
			},
			{
				'name': 'Transclusions',
				'value': data['transclusions']
			}
		] if data['results'] else None
	)

@app.route('/api', methods=['GET'])
def api():
	data = main()
	output = OrderedDict()

	def add_item(name):
		if data[name]:
			output[name] = data[name]

	if 'page' not in data and 'project' not in data:
		return render_template(
			'api.html',
			examples = [
				'page=Main_Page&project=en.wikipedia.org',
				'page=Wikipédia:Accueil_principal&project=fr.wikipedia.org',
				'page=Category:Main Page&project=en.wikipedia.org',
				'page=File:Example.png&project=en.wikipedia.org',
			]
		)

	if 'errors' in data:
		output['errors'] = data['errors']
	else:
		add_item('filelinks')
		add_item('categorylinks')
		add_item('wikilinks')
		add_item('redirects')
		add_item('transclusions')

	return json.dumps(output)

if __name__ == '__main__':
	app.run()