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
from flask import Flask, render_template, send_from_directory, request
import MySQLdb

app = Flask(__name__)

app.jinja_env.filters['sepnum'] = lambda x: '{:,}'.format(x)

class Fetcher:
	def __init__(self, cur, namespace, title):
		self.cur = cur
		self.namespace = namespace
		self.title = title

	def fetch(self, table, ns_key, title_key, no_redirects = False):
		self.cur.execute(
			'SELECT COUNT(*) FROM %s WHERE %s=%%s AND %s=%%s'
			% (table, ns_key, title_key),
			(self.namespace, self.title)
		)
		count = self.cur.fetchone()[0]

		if not no_redirects:
			self.cur.execute(
				'SELECT COUNT(*) FROM redirect '
				'JOIN page ON rd_from=page_id '
				'JOIN %s ON %s=page_namespace AND %s=page_title '
				'WHERE rd_namespace=%%s AND rd_title=%%s'
				% (table, ns_key, title_key),
				(self.namespace, self.title)
			)

			redirectcount = self.cur.fetchone()[0]
			return (count, count + redirectcount)

		return count

	def fetch_wo_ns(self, table, to_key, no_redirects = False):
		self.cur.execute(
			'SELECT COUNT(*) FROM %s WHERE %s=%%s'
			% (table, to_key),
			(self.title,)
		)
		count = self.cur.fetchone()[0]

		if not no_redirects:
			self.cur.execute(
				'SELECT COUNT(*) FROM redirect '
				'JOIN page ON rd_from=page_id '
				'JOIN %s ON %s=page_title '
				'WHERE rd_namespace=%%s AND rd_title=%%s'
				% (table, to_key), (self.namespace, self.title)
			)
			redirectcount = self.cur.fetchone()[0]
			return (count, count + redirectcount)

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
		title = page.replace(' ', '_').capitalize()
 
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
	total = [0, 0]

	pagelinks = fetcher.fetch('pagelinks', 'pl_namespace', 'pl_title')
	total[0] += pagelinks[0]
	total[1] += pagelinks[1]

	redirects = fetcher.fetch('redirect', 'rd_namespace', 'rd_title', True)
	total[0] += redirects
	total[1] += redirects
	# Don't count double redirects

	wikilinks = (pagelinks[0] - redirects, pagelinks[1] - redirects)

	transclusions = fetcher.fetch('templatelinks', 'tl_namespace', 'tl_title')
	total[0] += transclusions[0]
	total[1] += transclusions[1]

	filelinks = False

	if namespace == 6:
		filelinks = fetcher.fetch_wo_ns('imagelinks', 'il_to', True)
		total[0] += filelinks
		total[1] += filelinks
		# Images links from redirects are also added to the imagelinks table under the redirect target

	categorylinks = False

	if namespace == 14:
		categorylinks = fetcher.fetch_wo_ns('categorylinks', 'cl_to')
		total[0] += categorylinks[0]
		total[1] += categorylinks[1]

	cur.close()
	db.close()

	return {
		'title': page + ' on ' + project + ' - Link Count',
		'page': page,
		'project': project,
		'results': True,
		'total': total,
		'pagelinks': pagelinks,
		'wikilinks': wikilinks,
		'redirects': redirects,
		'transclusions': transclusions,
		'filelinks': filelinks,
		'categorylinks': categorylinks,
	}

@app.route('/', methods=['GET'])
def index():
	return render_template('main.html', **main())

@app.route('/api', methods=['GET'])
def api():
	data = main()
	output = OrderedDict()

	def add_item(name):
		if data[name]:
			output[name] = data[name][0]

	def add_single_item(name):
		if data[name]:
			output[name] = data[name]

	def add_redirect_item(name):
		if data[name]:
			output['withredirects'][name] = data[name][1]

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
		add_item('total')
		add_single_item('filelinks')
		add_item('categorylinks')
		add_item('pagelinks')
		add_item('wikilinks')
		add_single_item('redirects')
		add_item('transclusions')

		output['withredirects'] = OrderedDict()
		add_redirect_item('total')
		add_redirect_item('categorylinks')
		add_redirect_item('pagelinks')
		add_redirect_item('wikilinks')
		add_redirect_item('transclusions')

	return json.dumps(output)

if __name__ == '__main__':
	app.run(debug=True)

@app.route('/static/oojs-ui.css', methods=['GET'])
def oojs_ui_css():
	return send_from_directory('node_modules', 'oojs-ui/dist/oojs-ui-wikimediaui.min.css')