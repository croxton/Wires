#Wires

* Author: [Mark Croxton](http://hallmark-design.co.uk/)

## Version 2.0.1 (beta)

* Requires: ExpressionEngine 2

## Description

Wire up your forms to URI segments. Search, filter sort and order entries with clean, friendly uris that can be cached and bookmarked.

## Features

* Map form fields to URI segments and query strings
* Re-populate your form fields from the URI
* Safety first: validate and sanitize user-submitted data with regular expressions 
* Use with Stash and Low Search to create advanced ecommerce style filtering

## Installation

1. [Download Wires](https://github.com/croxton/Wires/archive/master.zip) and un-zip
2. Move the folder 'wires' into ./system/expressionengine/third_party/

##Tags:

* {exp:wires:connect}
* {exp:wires:url}

##Advanced example:

		{!-- 
		Generate and cache a list of categories, with Stash.
		We'll use to output options in the form and map category ids to category url titles 
		--}
		{exp:stash:set_list name="categories" parse_tags="yes" save="yes" scope="site" replace="no" refresh="0"}  
			{exp:channel:categories channel="products" style="linear" category_group="2"}
				{stash:category_id}{category_id}{/stash:category_id}
				{stash:category_url_title}{category_url_title}{/stash:category_url_title}
				{stash:category_name}{category_name}{/stash:category_name}
			{/exp:channel:categories}
		{/exp:stash:set_list}

		{!-- connect the wires --}
		{exp:wires:connect 
			url="/products/search/{category}/{price}/{color}/{order_by}/{sort}/?search={search}" 
			action="/products/search"
			id="search"
			form="no"
			prefix="search"
			parse="inward"
			
			{!-- 'category' --}
			+category="multiple"
			+category:match="#^[0-9]+$#"
			+category:default_in="any"
			+category:default_out=""
			+category:delimiter_in="-or-"
			+category:delimiter_out="|"
			+category:map="{exp:stash:get_list name='categories' backspace='1'}{category_url_title}:{category_id};{/exp:stash:get_list}"

			{!-- 'price' (price_min and price_max fields) --}
			+price="range"
			+price:default_in="any-price"
			+price:default_out=""
			+price:delimiter_in="-to-"
			+price:delimiter_out=";"
			+price:from="at-least-"
			+price:to="at-most-"

			{!-- 'color' --}
			+color="single"
			+color:match="#^[A-Za-z-_ ]+$#"
			+color:default_in="any"
			+color:default_out=""

			{!-- 'search' --}
			+search="single"
			+search:default_in=""
			+search:default_out=""

		    {!-- 'orderby' --}
		    +orderby="single"
		    +orderby:match="#^title$|^price$#"
		    +orderby:default_in="sort_by_price"
		    +orderby:default_out="price"
		    +orderby:map="sort_by_title:title;sort_by_price:price"

		    {!-- 'sort' --}
		    +sort="single"
		    +sort:match="#^asc$|^desc$#"
		    +sort:default_in="asc"
		    +sort:default_out="asc"
		}
			<form action="" method="post">

				<fieldset>

					<label for="category">Category</label>
					<select name="category[]" id="category" multiple="multiple">
					{exp:stash:get_list name="categories" scope="site"} 
					   	<option value="{category_id}"{if category_id ~ '/(^|\|)'.category.'($|\|)/'} selected="selected"{/if}>{category_name}</option>
					{/exp:stash:get_list}
					</select>

					<label for="price_min">Min price</label>
					<select name="price_min" id="price_min">
						<option value="100"{if "100" == "{price_min}"} selected="selected"{/if}>100</option>
						<option value="200"{if "200" == "{price_min}"} selected="selected"{/if}>200</option>
						<option value="300"{if "200" == "{price_min}"} selected="selected"{/if}>300</option>
					</select>

					<label for="price_max">Max price</label>
					<select name="price_max" id="price_max">
						<option value="100"{if "100" == "{price_max}"} selected="selected"{/if}>100</option>
						<option value="200"{if "200" == "{price_max}"} selected="selected"{/if}>200</option>
						<option value="300"{if "300" == "{price_max}"} selected="selected"{/if}>300</option>
					</select>

					<label for="color">Color</label>
					<select name="color" id="color">
						<option value="red"{if "red" == "{color}"} selected="selected"{/if}>Red</option>
						<option value="blue"{if "blue" == "{color}"} selected="selected"{/if}>Blue</option>
						<option value="green"{if "green" == "{color}"} selected="selected"{/if}>Green</option>
					</select>

					<label for="search">Search</label>
					<input type="text" name="search" id="search" value="{search}">

					<label for="orderby">Order by</label>
					<select name="orderby" id="orderby">
						<option value="price"{if "price" == "{orderby}"} selected="selected"{/if}>Price</option>
						<option value="title"{if "title" == "{orderby}"} selected="selected"{/if}>Title</option>
					</select>

					<label for="sort">Sort</label>
					<select name="sort" id="sort">
						<option value="asc"{if "asc" == "{sort}"} selected="selected"{/if}>Ascending</option>
						<option value="desc"{if "desc" == "{sort}"} selected="selected"{/if}>Descending</option>
					</select>

				</fieldset>

			</form>

			{exp:low_search:results 
		        collection="products"
		        category = "{category}"
		        range:cf_price = "{price}"
		        search:cf_color = "{color}"
		        keywords = "{search}"
		        orderby="{orderby}"
		        sort="{sort}"
		        limit="10"
		        status="open"
		        disable="member_data"
		    }
		        {if search:no_results}
		            No results
		        {/if}
		        {if search:count==1}
		        <table class="results">
		            <thead>
		                <tr>
		                    <th>Product</th>
		                    <th>Price</th>
		                </tr>
		            </thead>
		            <tbody>
		        {/if}
		                <tr class="results-row{search:switch='|-alt'}">
		                    <td><a href="{title_permalink='products'}">{title}</a></td>
		                    <td>{cf_price}</td>
		                </tr>
		        {if search:count==search:total_results}    
		            </tbody>
		        </table>
		        {/if}
		    {/exp:low_search:results}
		{/exp:wires:connect}
